<?php

namespace RestMachine;


use Symfony\Component\HttpFoundation\Response;

class WebMachineTest extends WebMachineTestCase {

    static function validateJson(Context $context) {
        if ($context->getRequest()->getContent()) {
            $context->entity = json_decode($context->getRequest()->getContent());
            return json_last_error();
        }
        return false;
    }

    function testMinimalResource() {
        $resource = Resource::create();
        $this->assertStatusCode(Response::HTTP_OK, $this->GET($resource));
    }

    function testAllowedMethods() {
        $resource = Resource::create()->allowedMethods(['POST']);
        $this->assertStatusCode(Response::HTTP_METHOD_NOT_ALLOWED, $this->GET($resource));
    }

    function testMalformed() {
        $resource = Resource::create()
            ->allowedMethods(['GET', 'POST'])
            ->isMalformed([self::class, 'validateJson']);

        $this->assertStatusCode(Response::HTTP_OK, $this->GET($resource));

        $this->assertStatusCode(Response::HTTP_BAD_REQUEST,
            $this->POST($resource, 'invalid json'));

        $this->assertStatusCode(Response::HTTP_CREATED,
            $this->POST($resource, json_encode(['foo' => 'bar'])));
    }

    function testUnprocessable() {
        $resource = Resource::create()
            ->allowedMethods(['GET', 'POST'])
            ->isMalformed([self::class, 'validateJson'])
            ->isProcessable(function(Context $context) {
                return !$context->entity || isset($context->entity->foo);
            });

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY,
            $this->POST($resource, json_encode(['foobar' => 'baz'])));

        $this->assertStatusCode(Response::HTTP_CREATED,
            $this->POST($resource, json_encode(['foo' => 'bar'])));
    }

    function testMediaTypeNegotiation() {
        $data = ['foo' => 'bar'];
        $resource = Resource::create()
            ->availableMediaTypes(['application/json', 'application/php'])
            ->handleOk($data);

        $this->assertStatuscode(Response::HTTP_NOT_ACCEPTABLE,
            $this->GET($resource, ['Accept' => 'text/html']));

        $this->assertEquals(json_encode($data),
            $this->GET($resource, ['Accept' => 'application/json'])->getContent());

        $this->assertEquals(serialize($data),
            $this->GET($resource, ['Accept' => 'application/php'])->getContent());
    }

    function testMediaTypeNegotiationWithQualityFactor() {
        $resource = Resource::create()
            ->availableMediaTypes(['text/plain', 'text/html'])
            ->handleOk(function(Context $context) {
                $type = $context->getMediaType();
                $message = "Hello World!\nHow are you doing?";
                return $type == 'text/html' ? nl2br($message) : $message;
            });
        $this->assertEquals("Hello World!\nHow are you doing?",
            $this->GET($resource, ['Accept' => 'text/html; q=0.9, text/plain'])
                ->getContent());

        $this->assertEquals("Hello World!<br />\nHow are you doing?",
            $this->GET($resource, ['Accept' => 'text/plain; q=0.8, text/html'])
                ->getContent());
    }

    function testSimpleJsonPost() {
        $resource = Resource::create()
            ->availableMediaTypes(['application/json'])
            ->allowedMethods(['POST'])
            ->isMalformed([self::class, 'validateJson'])
            ->post(function(Context $context) {
                $context->newEntity = clone $context->entity;
                $context->newEntity->id = 42;
            })
            ->handleCreated(function(Context $context) {
                return $context->newEntity;
            });

        $entity = ['name' => 'foo bar'];
        $response = $this->POST($resource, json_encode($entity));
        $this->assertEquals(json_encode(array_merge($entity, ['id' => 42])), $response->getContent());
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    function testIfModifiedSinceConditionalRequest() {
        $lastModified = new \DateTime();
        $resource = Resource::create()->lastModified($lastModified);

        $response = $this->GET($resource, ['If-Modified-Since' => Utils::httpDate($lastModified)]);
        $this->assertStatusCode(Response::HTTP_NOT_MODIFIED, $response);
        $this->assertEquals(Utils::httpDate($lastModified),
            $response->headers->get('Last-Modified'));

        $ifModSince = clone $lastModified;
        $ifModSince->modify('-1 hour');
        $response = $this->GET($resource, ['If-Modified-Since' => Utils::httpDate($ifModSince)]);
        $this->assertStatusCode(Response::HTTP_OK, $response);
        $this->assertEquals(Utils::httpDate($lastModified),
            $response->headers->get('Last-Modified'));
    }
}

