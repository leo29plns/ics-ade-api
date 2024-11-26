<?php

namespace App\Controller;

use ICal\ICal;
use App\Service\SchemaParser;
use App\Service\CalendarSerializer;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

class ApiController extends AbstractController
{
    const CACHE_DELAY_ICAL_OBJECT = 1800;
    const CACHE_DELAY_REQUEST = 3600;

    #[Route('/', name: 'app.api', methods: ['POST'])]
    #[OA\Post(
        path: "/",
        operationId: "index",
        summary: "Handles iCal data processing",
        description: "This endpoint processes iCal data, caches the results, and returns the processed events with their corresponding schemas.",
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "object",
                required: ["url", "schemas"],
                properties: [
                    new OA\Property(property: "url", type: "string", description: "The URL to fetch the iCal data from"),
                    new OA\Property(property: "schemas", type: "array", items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "property", type: "string", description: "The property to extract from the iCal event (e.g., description, summary)"),
                            new OA\Property(property: "fields", type: "array", items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "name", type: "string", description: "The field name in the iCal event"),
                                    new OA\Property(property: "pattern", type: "string", description: "The regular expression pattern used to extract data from the field"),
                                    new OA\Property(property: "type", type: "string", enum: ["string", "array", "int", "integer", "float", "double", "bool", "boolean"], description: "The type of the field"),
                                    new OA\Property(property: "arrayType", type: "string", description: "The type of elements in the array (optional, only if type is array)")
                                ]
                            ))
                        ]
                    ))
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The processed iCal data with events and schemas",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "cachedAt", type: "integer", description: "Timestamp when the response was cached"),
                new OA\Property(property: "cacheDelay", type: "integer", description: "The delay before the cache expires"),
                new OA\Property(property: "data", type: "object", description: "The serialized calendar data")
            ]
        )
    )]
    public function index(Request $request, SchemaParser $schemaParser, CacheInterface $cache): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['url']) || !isset($data['schemas'])) {
            return new JsonResponse(['error' => 'Missing "url" or "schema" key in the request body'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $cacheKey = 'request_' . md5($data['url'] . json_encode($data['schemas']));
        $icalCacheKey = 'ical_object_' . md5($data['url']);

        $cachedResponse = $cache->get($cacheKey, function (ItemInterface $item) use ($data, $schemaParser, $icalCacheKey, $cache) {

            $schemaParser->decodeSchemas($data['schemas']);
        
            $ical = $cache->get($icalCacheKey, function (ItemInterface $item) use ($data) {
                try {
                    $ical = new ICal();
                    $ical->initUrl($data['url']);
                    $item->expiresAfter(self::CACHE_DELAY_ICAL_OBJECT);
                    return $ical;
                } catch (\Exception $e) {
                    throw new \Exception('Failed to fetch or parse the iCal data: ' . $e->getMessage());
                }
            });
        
            $events = $ical->events();
            $result = [];
            foreach ($events as $event) {
                $result[$event->uid] = $schemaParser->searchSchemas($event);
            }

            $calendarSerialiser = new CalendarSerializer($ical, $result);
            $serializedData = $calendarSerialiser->serialize();

            $item->expiresAfter(self::CACHE_DELAY_REQUEST);
            return [
                'cachedAt' => time(),
                'cacheDelay' => self::CACHE_DELAY_REQUEST,
                'data' => $serializedData
            ];
        });

        $maxAge = max(0, $cachedResponse['cachedAt'] + $cachedResponse['cacheDelay'] - time());

        $response = new JsonResponse($cachedResponse, JsonResponse::HTTP_OK);
        $response->headers->set('Cache-Control', 'public, max-age=' . $maxAge);

        return $response;
    }
}
