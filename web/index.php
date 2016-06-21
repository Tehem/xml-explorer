<?php

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tehem\Xml\Browser;

require_once __DIR__ . '/../vendor/autoload.php';

$filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

$app = new Silex\Application();

$app['debug'] = true;

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => realpath(__DIR__ . '/../') . '/dev.log',
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../app/views'
));

$app['directories'] = function () {
    return [
        'data' => realpath(__DIR__ . '/../data')
    ];
};

$app['xmlReader'] = function () {
    return new XMLReader();
};

$app['xmlStreamer'] = function ($app) {
    return new \Tehem\Xml\Streamer($app['xmlReader'], $app['logger']);
};

$app->get('/', function (Silex\Application $app) {

    $directories = $app['directories'];
    $directoryInfos = array();

    foreach ($directories as $dirName => $directory) {
        $directoryInfos[] = array(
            'name' => $dirName,
            'path' => $directory,
            'count' => Browser::getFileCount($directory)
        );
    }

    return $app['twig']->render('index.html.twig', array(
        'directories' => $directoryInfos
    ));
});

$app->get('/xml/{dirName}', function(Silex\Application $app, $dirName) {

    $directories = $app['directories'];
    $files       = Browser::getFiles($directories[$dirName]);

    return $app['twig']->render('directory.html.twig', array(
        'directory' => $dirName,
        'files' => $files
    ));
});

$app->get('/xml/{dirName}/{fileName}', function (Silex\Application $app, $dirName, $fileName) {

    $directories = $app['directories'];
    $streamer = $app['xmlStreamer'];
    $streamer->setPath($directories[$dirName] . '/' . $fileName);

    return $app['twig']->render('file.html.twig', array(
        'directory' => $dirName,
        'file' => $fileName,
        'size' => filesize($directories[$dirName] . '/' . $fileName),
        'nodes' => $streamer->loadNode()
    ));
});


$app->get('/xml/{dirName}/{fileName}/download', function (Silex\Application $app, $dirName, $fileName) {
    // Generate response
    $directories = $app['directories'];
    $filePath = $directories[$dirName] . '/' . $fileName;
    $response = new Response();

    // Set headers
    $response->headers->set('Cache-Control', 'private');
    $response->headers->set('Content-type', mime_content_type($filePath));
    $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($filePath) . '";');
    $response->headers->set('Content-length', filesize($filePath));

    // Send headers before outputting anything
    $response->sendHeaders();

    $response->setContent(readfile($filePath));
});

$app->get('/xml/{dirName}/{fileName}/node/{path}', function (Silex\Application $app, Request $request, $dirName, $fileName, $path) {

    $directories = $app['directories'];
    $streamer = $app['xmlStreamer'];

    if (null == $path) {
        $nodePath = null;
        $nodeSequence = 1;
        $nodeDepthStep = 1;

    } else {
        $pathParts = explode('#', $path);
        $nodePath = '/' . $pathParts[0];
        $nodeSequence = $pathParts[1];
        $nodeDepthStep = $pathParts[2];
    }

    $streamer->setPath($directories[$dirName] . '/' . $fileName);
    $node = $streamer->loadNode($nodePath, $nodeSequence, $nodeDepthStep);

    if (!$request->isXMLHttpRequest()) {
        return new Response('This call is supposed to be an ajax call', 400);
    }

    return new JsonResponse(
        array(
            'directory' => $dirName,
            'file' => $fileName,
            'node' => $nodePath,
            'content' => $node
        )
    );
})
    ->assert('path', '.+')
    ->value('path', null);


$app->error(function (\Exception $e, Request $request, $code) {
    switch ($code) {
        case 404:
            $message = '404: The requested page could not be found.';
            break;
        default:
            $message = $code . ': We are sorry, but something went terribly wrong.';
    }

    return new Response($message);
});

$app->run();