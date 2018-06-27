# Two-Factor Authentication (2FA)
### ⏱ 15 Min Build Time

Enterprises are increasingly challenged to keep sensitive information from falling into the wrong hands. This means that the traditional online authentication systems that rely solely on usernames and passwords are no longer up to scratch as security breaches grow in frequency, severity and sophistication.

Implementations of two-factor authentication (2FA) provide an additional layer of account security by verifying the user's password with a second authentication token. The most common use case of 2FA involves the application of one-time passwords (OTP) generated by hardware tokens or authenticator apps or directly sent to the user's mobile phone via SMS text messaging. SMS-based 2FA has the advantage of not requiring additional software or hardware on the user's end. With the [MessageBird Verify API](https://www.messagebird.com/en/verify), you can implement 2FA and OTP solutions to secure customer data, block fraudulent accounts, and safeguard key transactions in a matter of minutes.

In this guide, we'll introduce the [MessageBird Verify API](https://www.messagebird.com/en/verify) and show you how to build a runnable application in PHP. The application is a prototype for a two-factor authentication system deployed by a fictitious online banking application called _BirdBank_.

We will walk you through the following steps:
    
* Asking for the phone number
* Sending a verification code
* Verifying the code

You can follow along this tutorial to build the whole application from scratch or, if you want to see it in action right away, you can also download, clone or fork the sample application from [the MessageBird GitHub repository](https://github.com/messagebirdguides/verify-guide-php)

## Getting Started

First things first, we need to have PHP installed to run this sample 2FA application. If you're using a Mac, PHP will already be installed. For Windows users, you can download PHP from [windows.php.net](https://windows.php.net/download/). For Linux users, please check your system's default package manager. You also need Composer to install the [MessageBird SDK for PHP](https://github.com/messagebird/php-rest-api) and other dependencies, which is available from [getcomposer.org](https://getcomposer.org/download/).

Download the sample application by cloning [the MessageBird GitHub repository](https://github.com/messagebirdguides/verify-guide-php) or retrieving and extracting the ZIP file.

Next, let's open a console pointed at the directory into which you've stored the sample application and run the following command:

````bash
composer install
````

Apart from the MessageBird SDK, Composer will install the [Slim framework](https://packagist.org/packages/slim/slim), the [Twig templating engine](https://packagist.org/packages/slim/twig-view), and the [Dotenv configuration library](https://packagist.org/packages/vlucas/phpdotenv). We're using these libraries to add some structure to the project while keeping the sample easy to understand and without the overhead of a full-scale web framework.

## Configuring the MessageBird SDK

The SDK is defined as a dependency in `composer.json`:

````json
{
    "require" : {
        "messagebird/php-rest-api" : "^1.9.4"
        ...
    }
}
````

Composer autoloading makes the SDK available to the application and is initialized by creating an instance of the `MessageBird\Client` class. The constructor takes a single argument: an API key. For our Slim-based example, we add the SDK on the dependency injection container:

````php
// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};
````

**Pro-tip**: Hardcoding your credentials in the code is a risky practice that should never be used in production applications. A better method, also recommended by the Twelve-Factor App Definition, is to use environment variables. We've added dotenv to the sample application, so you can supply your API key in a file named .env, too.

So let's use `getenv()` to load the API key from the environment variable. To make the key available in the environment variable we need to initialize Dotenv and then add the key to a `.env` file. You can copy the `env.example` file provided in the repository to `.env` and then add your API key like this:

````env
MESSAGEBIRD_API_KEY=YOUR-API-KEY
````

API keys can be created or retrieved from the [API access (REST) tab](https://dashboard.messagebird.com/en/developers/access) in the _Developers_ section of your MessageBird account.

## Asking for the Phone Number

The first step in verifying a user's phone number is asking them to provide their phone number. In `views/step1.html.twig` you'll find a basic HTML form with a single input field and a button to submit the data using the POST method to `/step2`. Providing `tel` as the `type` attribute of our input field allows some browsers, especially on mobile devices, to optimize for telephone number input, for example by displaying a number pad.

The following route in `index.php` displays the form:

````php
// Display page to ask the user for their phone number
$app->get('/', function($request, $response) {
    return $this->view->render($response, 'step1.html.twig');
});
````

## Sending a Verification Code

Once we've collected the number, we can send a verification message to our user's mobile device. The MessageBird Verify API takes care of generating a random token, so you don't have to do this yourself. Codes are numeric and six digits by default. If you want to customize the length of the code or configure other options, you can check out the [Verify API documentation](https://developers.messagebird.com/docs/verify#verify-request).

The form we created in the last step submits the phone number to `/step2`, so let's define this route in our `index.php`:

````php
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
````

Let's quickly dive into what happens here:

First, we're creating a `MessageBird\Objects\Verify` object to encapsulate the parameters for our API request. We set the number from the form as the `recipient` parameter and specify a template for the message. The template contains the placeholder `%token`, which is replaced with the generated token on MessageBird's end. If we omitted this, the message would contain the token and nothing else.

The MessageBird SDK throws exceptions for any error. Therefore, the next section is contained in a try-catch block. Using `$this->messagebird` we access the previously initialized SDK object and then call the `verify->create()` method with the parameter object. If the API call fails for any reason, for example, because the user has entered an invalid phone number, the catch-block executes, and we render the form from the first step again but include the error message. In production applications, you'd most likely not expose the raw API error. Instead, you could consider different possible problems and return an appropriate message in your own words. You might also want to prevent some errors from happening by doing some input validation on the phone number yourself. 

In case the request was successful, code execution continues after the catch-block, and we'll render a new page. Our API response contains an ID, which we'll need for the next step, so we'll add it to the form. Since the ID is meaningless without your API access key, there are no security implications of doing so. However, in practice, you'd be more likely to store this ID in a session object on the server. You can see the form in the file `views/step2.html.twig`. It looks similar to the previous form but includes a hidden field with our verification ID.

## Verifying the Code

Once the code is delivered, our user will check their phone and enter the code into our form. Next, we'll send the user's input along with the ID of the verification request to MessageBird's API and see whether the verification was successful or not. Let's declare this third step as a new route in our `index.php`:

````php
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
````

In this step, we retrieve the `id` and `token` inputs from the form. The `verify->verify()` method accepts these two parameters, so we do not need to create an object first but pass them directly. As before, there's a catch-block to handle errors such as an invalid token entered by the user. In this case, we show the same form again with the error.

If verification was successful, we render a simple confirmation page. The template `views/step3.html.twig` only contains a static message.

## Testing

You can test the application with PHP's built-in web server. Enter the following command on the console to start:

````bash
php -S 0.0.0.0:8080 index.php
````

Point your browser to [http://localhost:8080/](http://localhost:8080/) and try to verify your own phone number.

## Nice work!

You now have a running integration of MessageBird's Verify API using PHP!

You can now leverage the flow, code snippets and UI examples from this tuto-rial to build the verification into a real application's register and login process to enable 2FA for it.

## Next steps

Want to build something similar but not quite sure how to get started? Please feel free to let us know at support@messagebird.com, we'd love to help!
