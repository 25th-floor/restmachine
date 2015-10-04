# How It Works

## Decisions, Decisions

RestMachine is built on a simple decision tree. This tree can have three different kinds of nodes. Every node
has a name and is represented as just a key-value-pair in an array.

```php
$decisions = [
  // decision
  'malformed?' => ['handle-malformed', 'authorized?'],

  // action
  'post!' => 'post-redirect?',

  // handler
  'handle-malformed' => 400,
];

```

RestMachine starts with `service-available?` and just walks the tree until it hits a handler.

For every decision node it looks in the resource specification for that node and just evaluates it.

## Resource Specification

Resource specification example:

```php
$resource = [
    'malformed?' => function($context) {
        // do some validation on the request body here
        // you can get the request via the $context param
        return false;
    },
    'service-available?' => true
];

```

The resource specification values can be scalars or callable. If a node is not present it just evaluates
to false, if it's a scalar it evaluates that scalar in a truthy context, and if it's a callable it uses the
return value of that callable as the decision result.

RestMachine has sensible default values for most decisions so you don't have to specify
`service-available?` and things like that all the time.

## Resource Builder

For actually creating a resource specification RestMachine provides the `Resource` class which internally
just builds up an array like the one shown above, so your IDE can better help you remember
all those node names.

```php
$resource = Resource::create()
  ->isMalformed(function($context) {
      return false;
  })
  ->isServiceAvailable(true)
```

This creates the same resource array as shown above except it will be merged with the builtin defaults
(so `isServiceAvailable(true)` is superfluous here because it defaults to true).

### Directives

Directives are entries in the resource specification that don't correspond to nodes in the decision tree.
Those are used as configuration of default decisions. Like any other value in the resource specification
it can be a scalar or a callable so the value can depend on the actual request. For example:

```php
// those are both equivalent
Resource::create()->allowedMethods(['GET']);
// when using a callable you can inspect the context (which also gives
// you access to the request object) to decide on the return value
Resource::create()->allowedMethods(function($context) {
  return ['GET'];
})
```

This array will be used by the default implementation of `method-allowed?` which will try to match the actual
request method with one in the array.

#### availableMediaTypes

The value for this directive must evaluate to an array of strings. This array is used by the default implementation
of `media-type-available?` to negotiate the content type of the response.

Example:

```php
Resource::create()->availableMediaTypes(['application/json', 'text/csv']);
```

#### allowedMethods

The value for this directive must also evaluate to an array of strings. This array should contain a list of 
allowed HTTP methods. RestMachine will try to match those against the actual HTTP request method in its 
default implementation of the `method-allowed?` decision.

Example:

```php
Resource::create()->allowedMethods(['GET', 'HEAD']);
```

#### lastModified

The value for this directive must be a `DateTime` instance. It is used by the default implementations
of the `modified-since?` and `unmodified-since?` decisions to implement
`If-Modified-Since` and `If-Unmodified-Since` semantics.

```php
Resource::create()->lastModified(new \DateTime('2015-10-01'));
```

#### etag

The etag value must evaluate to a string. This value is used to implement `If-Match` and `If-None-Match` semantics by
the default implementations of the `etag-matches-for-if-none?` and `etag-matches-for-if-match?` decisions.

```php
Resource::create()->etag('aXs3f');
```

## Context

RestMachine provides a context object that lives for the duration of request execution. This context
is passed to all callable values and can be used to obtain the actual HTTP request object.
Furthermore it is used to gather information during decision functions that may be relevant later.
RestMachine internally uses this context for content negotiation and conditional requests. You can put
arbitrary values into the context to communicate with later running decisions, actions or handlers.

Example:

```php
Resource::create()
    ->isMalformed(function($context) {
        if ($context->getRequest()->isMethod('POST')) {
            $requestBody = $context->getRequest()->getContent();
            // validate request body
            if (!isValid($requestBody)) {
                return true;
            }
            // parse request body
            $context->entity = parseRequestBody($requestBody);
        }
        return false;
    })
    ->post(function($context) {
        // do something with the entity we put into context
        // during the malformed? decision
        $db->insert($context->entity);
    });
```