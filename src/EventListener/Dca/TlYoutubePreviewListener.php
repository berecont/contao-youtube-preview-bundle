<?php

declare(strict_types=1);

namespace Berecont\ContaoYoutubePreview\EventListener\Dca;

use Contao\DataContainer;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Dbafs;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * onsubmit_callback für tl_youtube_preview
 */
class TlYoutubePreviewListener
{
    public function __construct(
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onSubmit(DataContainer $dc): void
    {
        $this->log('onSubmit triggered.');

        try {
            $id = (int)($dc->id ?? 0);
            if ($id <= 0) {
                $this->log('No valid ID on DataContainer.');
                return;
            }

            $row = Database::getInstance()
                ->prepare('SELECT id, youtube_id, targetFolder FROM tl_youtube_preview WHERE id=?')
                ->limit(1)
                ->execute($id);

            if (!$row->numRows) {
                $this->log('Record not found for id=' . $id);
                return;
            }

            $youtubeId    = (string) $row->youtube_id;
            $targetFolder = (string) $row->targetFolder; // binary(16)

            $this->log('Values: youtube_id=' . $youtubeId . ', targetFolder(b64)=' . base64_encode($targetFolder));

            if ($youtubeId === '' || $targetFolder === '') {
                $this->log('Missing youtube_id or targetFolder, abort.');
                return;
            }

            // Zielordner (tl_files)
            $folderUuid    = StringUtil::binToUuid($targetFolder);
            $folderModel   = FilesModel::findByUuid($folderUuid);
            if (null === $folderModel) {
                $this->log('FilesModel for targetFolder not found (uuid=' . $folderUuid . ').');
                return;
            }
            if ($folderModel->type !== 'folder') {
                $this->log('Target is not a folder (path=' . $folderModel->path . ').');
                return;
            }

            $relativeFolderPath = $folderModel->path; // z.B. files/youtube-previews
            $projectDir         = System::getContainer()->getParameter('kernel.project_dir'); // absoluter Projektpfad
            $absoluteFolderPath = rtrim($projectDir, '/') . '/' . ltrim($relativeFolderPath, '/');

            $this->log('Folder paths: relative=' . $relativeFolderPath . ', absolute=' . $absoluteFolderPath);

            if (!is_dir($absoluteFolderPath)) {
                $this->filesystem->mkdir($absoluteFolderPath);
                $this->log('Created missing folder: ' . $absoluteFolderPath);
            }

            // Kandidaten-URLs
            $candidates = [
                sprintf('https://img.youtube.com/vi/%s/maxresdefault.jpg', $youtubeId),
                sprintf('https://i.ytimg.com/vi/%s/maxresdefault.jpg',   $youtubeId),
                sprintf('https://img.youtube.com/vi/%s/hqdefault.jpg',   $youtubeId),
                sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg',       $youtubeId),
            ];

            $imageBinary = $this->downloadFirstAvailable($candidates);

            if ($imageBinary === null) {
                $this->log('All thumbnail downloads failed. Writing a small marker file for diagnostics.');
                // kleine Markerdatei, damit wir sofort sehen, ob Schreiben & Pfade stimmen
                $marker = rtrim($absoluteFolderPath, '/') . '/__yt_preview_error_' . date('Ymd_His') . '.txt';
                $this->filesystem->dumpFile($marker, "Failed to download YouTube thumbnail for $youtubeId");
                return;
            }

            //$filename     = sprintf('%s_preview_%s.jpg', $youtubeId, date('Ymd_His'));
            $filename     = sprintf('youtubethumb-%s.jpg', $youtubeId);
            $relativePath = rtrim($relativeFolderPath, '/') . '/' . $filename;
            $absolutePath = rtrim($projectDir, '/') . '/' . ltrim($relativePath, '/');

            $this->log('Writing file: ' . $absolutePath);
            $this->filesystem->dumpFile($absolutePath, $imageBinary);

            // In DBAFS registrieren (RELATIVEN Pfad verwenden!)
            $fileModel = Dbafs::addResource($relativePath);
            if (null === $fileModel) {
                $this->log('Dbafs::addResource returned null for ' . $relativePath);
                return;
            }

            // UUID speichern
            Database::getInstance()
                ->prepare('UPDATE tl_youtube_preview SET generatedImage=? WHERE id=?')
                ->execute(StringUtil::uuidToBin($fileModel->uuid), $id);

            $this->log('Success. UUID set to generatedImage for id=' . $id . ' (uuid=' . $fileModel->uuid . ').');

        } catch (\Throwable $e) {
            $this->log('Exception: ' . $e->getMessage());
        }
    }

    /**
     * Download-Strategie mit mehreren Fallbacks:
     * 1) Symfony HttpClient (wenn Service vorhanden)
     * 2) file_get_contents (wenn allow_url_fopen aktiv)
     * 3) cURL (falls Erweiterung vorhanden)
     */
    private function downloadFirstAvailable(array $urls): ?string
    {
        foreach ($urls as $url) {
            // Versuch 1: Symfony HttpClient
            if ($this->httpClient) {
                try {
                    $res    = $this->httpClient->request('GET', $url, ['timeout' => 10]);
                    $status = $res->getStatusCode();
                    if ($status === 200) {
                        $headers = $res->getHeaders(false);
                        $ctArr   = $headers['content-type'] ?? $headers['Content-Type'] ?? [];
                        $ct      = strtolower(is_array($ctArr) ? ($ctArr[0] ?? '') : (string) $ctArr);
                        if (str_contains($ct, 'image')) {
                            $content = $res->getContent(false);
                            if (is_string($content) && $content !== '') {
                                $this->log('Downloaded via http_client: ' . $url);
                                return $content;
                            }
                        } else {
                            $this->log('http_client non-image content-type for ' . $url . ' => ' . $ct);
                        }
                    } else {
                        $this->log('http_client status ' . $status . ' for ' . $url);
                    }
                } catch (\Throwable $e) {
                    $this->log('http_client failed for ' . $url . ': ' . $e->getMessage());
                }
            }

            // Versuch 2: file_get_contents
            if (ini_get('allow_url_fopen')) {
                try {
                    $ctx = stream_context_create([
                        'http' => ['timeout' => 10, 'header' => "User-Agent: Contao-YouTubePreview\r\n"],
                        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
                    ]);
                    $data = @file_get_contents($url, false, $ctx);
                    if ($data !== false && strlen($data) > 0) {
                        $this->log('Downloaded via file_get_contents: ' . $url);
                        return $data;
                    }
                } catch (\Throwable $e) {
                    $this->log('file_get_contents failed for ' . $url . ': ' . $e->getMessage());
                }
            } else {
                $this->log('allow_url_fopen is disabled; skipping file_get_contents.');
            }

            // Versuch 3: cURL
            if (function_exists('curl_init')) {
                try {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_TIMEOUT        => 10,
                        CURLOPT_USERAGENT      => 'Contao-YouTubePreview',
                    ]);
                    $data = curl_exec($ch);
                    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $ct   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    $err  = curl_error($ch);
                    curl_close($ch);

                    if ($code === 200 && str_contains(strtolower($ct), 'image') && is_string($data) && $data !== '') {
                        $this->log('Downloaded via cURL: ' . $url);
                        return $data;
                    }
                    $this->log('cURL status=' . $code . ' ct=' . $ct . ' for ' . $url . ($err ? ' err=' . $err : ''));
                } catch (\Throwable $e) {
                    $this->log('cURL failed for ' . $url . ': ' . $e->getMessage());
                }
            } else {
                $this->log('cURL extension not available; skipping cURL.');
            }
        }

        return null;
    }

    private function log(string $message): void
    {
        $msg = '[YouTubePreview] ' . $message;
        if ($this->logger) {
            $this->logger->info($msg);
        } else {
            @error_log($msg);
        }
    }
}