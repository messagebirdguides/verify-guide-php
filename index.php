<?php

require_once "vendor/autoload.php";

// Create app
$app = new Slim\App;

// Load configuration with dotenv
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Get container
$container = $app->getContainer();

// Register Twig component on container to use view templates
$container['view'] = function() {
    return new Slim\Views\Twig('views');
};

// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};

// Display page to ask the user for their phone number
$app->get('/', function($request, $response) {
    return $this->view->render($response, 'step1.html.twig');
});

// Handle phone number submission
$app->post('/step2', function($request, $response) {
    // Create verify object
    $verify = new MessageBird\Objects\Verify;
    $verify->recipient = $request->getParsedBodyParam('number');
    $verify->template = "Your verification code is %token.";

    // Make request to Verify API
    try {
        $result = $this->messagebird->verify->create($verify);
    } catch (Exception $e) {
        // Request has failed
        return $this->view->render($response, 'step1.html.twig', [
            'error' => get_class($e).": ".$e->getMessage()
        ]);
    }

    // Request was successful, return step2 form
    return $this->view->render($response, 'step2.html.twig', [
        'id' => $result->getId()
    ]);
});

// Verify whether the token is correct
$app->post('/step3', function($request, $response) {
    $id = $request->getParsedBodyParam('id');
    $token = $request->getParsedBodyParam('token');

    // Make request to Verify API
    try {
        $this->messagebird->verify->verify($id, $token);
    } catch (Exception $e) {
        // Request has failed
        return $this->view->render($response, 'step2.html.twig', [
            'id' => $id,
            'error' => get_class($e).": ".$e->getMessage()
        ]);
    }

    // Request was successful
    return $this->view->render($response, 'step3.html.twig');
});

// Start the application
$app->run();