# restmachine

restmachine is a [webmachine](https://github.com/basho/webmachine) implementation for PHP.

Webmachine brings HTTP semantic awareness to your application. It allows you to declaratively
specify dynamic HTTP resources and frees you from having to worry about correct implementation of
the HTTP protocol and its semantics.

## Example

```php
  Resource::create()
    ->availableMediaTypes(['application/json', 'application/php'])
    ->allowedMethods(['GET', 'PUT'])
    ->exists(function($context) {
        $context->entity = $db->find($context->param('id'));
        return $context->entity !== null;
    })
    ->isMalformed(function($context) {
       if ($context->getRequest()->isMethod('PUT')) {
         $context->requestData = json_decode($context->getRequest()->getBody());
         return json_last_error();
       }
       return false;
    })
    ->isProcessable(function($context) {
        return $context->getRequest()->getMethod() != 'PUT'
          || $validator->isValid($context->requestData);
    })
    ->put(function($context) {
        $context->entity = $db->update($context->param('id'), $context->requestData);
    })
    ->handleOk(function($context) {
        return $context->entity;
    })
```

### Install

With composer:

```
{
  "require": {
    "steos/restmachine": "dev-master"
  }
}
```

restmachine currently requires PHP >= 5.5.

## Documentation

- [How It Works](doc/how-it-works.md)
- [Serialization](doc/serialization.md)
- [Content Negotiation](doc/content-negotiation.md)
- [Conditional Requests](doc/conditional-requests.md)
- [Debugging](doc/debugging.md)

### Examples

A fully baked example app using Silex can be found in the `examples/silex` directory.
It uses a sqlite database therefore you will need the sqlite PDO driver to run it.

```
# just serve the public directory through a webserver
$ php -S 0.0.0.0:8080 examples/silex/public/index.php

# and you can use the "/todos" resources
$ curl http://localhost:8080/todos -i -s -X POST -d '{"text":"switch to restmachine"}'
$ curl http://localhost:8080/todos/1 -i -s -X PUT -d '{"text":"switch to restmachine", "done":true}'
$ curl http://localhost:8080/todos/1 -i -s -X DELETE
$ curl http://localhost:8080/todos/1 -i -s

```

## Credits

Credits go to

- [clojure-liberator](http://clojure-liberator.github.io/liberator/)
  where we extracted the decision graph and which we heavily used as reference and documentation to understand the webmachine execution model

- [Symfony HttpFoundation](https://github.com/symfony/HttpFoundation)
  which RestMachine is built on.
   
## Project Status

This is alpha software. Some functionality is still missing.
There will be bugs. The API may still change, but should be fairly stable.

### TODO

- Content negotiation for language, charset, encoding
- Standalone wrapper with routing
- PATCH method

## License

Copyright © 2015 Stefan Oestreicher and contributors.

Distributed under the terms of the BSD-3-Clause license.
