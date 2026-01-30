<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sabre\DAV;
use Sabre\DAV\Auth\Backend\BasicCallBack;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\HTTP\Request as SabreRequest;
use Sabre\HTTP\Response as SabreResponse;

class WebDavController extends Controller
{
    public function handle(Request $request)
    {
        $root = storage_path('app/webdav');
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $locksPath = storage_path('app/webdav-locks');
        if (!is_dir($locksPath)) {
            mkdir($locksPath, 0755, true);
        }

        $server = new DAV\Server(new DAV\FS\Directory($root));
        $server->setBaseUri('/webdav/');

        // Auth disabled for testing

        $lockBackend = new DAV\Locks\Backend\File($locksPath . DIRECTORY_SEPARATOR . 'locks.dat');
        $server->addPlugin(new DAV\Locks\Plugin($lockBackend));

        $server->on('afterMethod', function (SabreRequest $req, SabreResponse $res) {
            $res->setHeader('MS-Author-Via', 'DAV');
            $res->setHeader('DAV', '1,2');
            $res->setHeader('X-MSDAVEXT', '1');
            $res->setHeader('Accept-Ranges', 'bytes');
            $res->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            $res->setHeader('Pragma', 'no-cache');
            $res->setHeader('Expires', '0');
            $res->setHeader(
                'Allow',
                'OPTIONS, GET, HEAD, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK'
            );
            $res->setHeader(
                'Public',
                'OPTIONS, GET, HEAD, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK'
            );
        });

        $logPath = storage_path('logs/webdav-requests.log');
        $logLine = sprintf(
            "[%s] %s %s UA=%s\n",
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getRequestUri(),
            $request->header('User-Agent', '-')
        );
        file_put_contents($logPath, $logLine, FILE_APPEND);

        $method = strtoupper($request->getMethod());
        if (in_array($method, ['PUT', 'LOCK', 'UNLOCK', 'PROPPATCH', 'MKCOL'], true)) {
            $relative = ltrim($request->path(), '/');
            if (str_starts_with($relative, 'webdav/')) {
                $relative = substr($relative, strlen('webdav/'));
            } elseif ($relative === 'webdav') {
                $relative = '';
            }

            $targetPath = $relative === ''
                ? $root
                : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (is_dir($root)) {
                @chmod($root, 0777);
            }

            $targetDir = is_dir($targetPath) ? $targetPath : dirname($targetPath);
            if (is_dir($targetDir)) {
                @chmod($targetDir, 0777);
            }

            if (is_file($targetPath)) {
                @chmod($targetPath, 0666);
            }
        }

        $headers = [];
        $userAgent = $request->header('User-Agent', '');
        foreach ($request->headers->all() as $name => $values) {
            $lower = strtolower($name);
            if ($userAgent !== '' && preg_match('/ms-office|msoffice|excel/i', $userAgent)) {
                if (in_array($lower, ['if-modified-since', 'if-none-match'], true)) {
                    continue;
                }
            }
            $headers[$name] = implode(',', $values);
        }

        $sabreRequest = new SabreRequest(
            $request->getMethod(),
            $request->getRequestUri(),
            $headers,
            $request->getContent()
        );

        $server->httpRequest = $sabreRequest;
        $server->httpResponse = new SabreResponse();

        $server->start();

        $sabreResponse = $server->httpResponse;
        $body = $sabreResponse->getBody();
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        $response = response($body ?? '', $sabreResponse->getStatus());
        foreach ($sabreResponse->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->set('MS-Author-Via', 'DAV');
        $response->headers->set('DAV', '1,2');
        $response->headers->set('X-MSDAVEXT', '1');
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set(
            'Allow',
            'OPTIONS, GET, HEAD, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK'
        );
        $response->headers->set(
            'Public',
            'OPTIONS, GET, HEAD, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK'
        );

        $statusLine = sprintf(
            "[%s] RESPONSE %s %s -> %d\n",
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getRequestUri(),
            $sabreResponse->getStatus()
        );
        file_put_contents($logPath, $statusLine, FILE_APPEND);

        $methodUpper = strtoupper($request->getMethod());
        if (in_array($methodUpper, ['OPTIONS', 'HEAD', 'GET'], true)) {
            $headersToLog = ['DAV', 'Allow', 'Public', 'MS-Author-Via', 'X-MSDAVEXT', 'Content-Type', 'Accept-Ranges'];
            $responseHeaders = $sabreResponse->getHeaders();
            foreach ($headersToLog as $headerName) {
                $value = $responseHeaders[$headerName] ?? $responseHeaders[strtolower($headerName)] ?? null;
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                $line = sprintf(
                    "[%s] HEADER %s %s -> %s\n",
                    date('Y-m-d H:i:s'),
                    $methodUpper,
                    $headerName,
                    $value === null ? '-' : $value
                );
                file_put_contents($logPath, $line, FILE_APPEND);
            }
        }

        return $response;
    }
}
