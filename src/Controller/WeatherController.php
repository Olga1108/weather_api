<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;

#[Route('/api')]
class WeatherController extends AbstractController
{
	private string $weatherApiKey;

	public function __construct(
		private HttpClientInterface $httpClient,
		private LoggerInterface $logger,
		string $weatherApiKey
	) {
		$this->weatherApiKey = $weatherApiKey;
	}

	#[Route('/weather', name: 'app_weather_get', methods: ['GET'])]
	#[OA\Get(
		path: '/api/weather',
		summary: 'Get current weather for a city',
		description: 'Returns the current weather forecast for the specified city using WeatherAPI.com.',
		operationId: 'getWeather',
		tags: ['weather']
	)]
	#[OA\Parameter(
		name: 'city',
		in: 'query',
		description: 'City name for weather forecast',
		required: true,
		schema: new OA\Schema(type: 'string')
	)]
	#[OA\Response(
		response: 200,
		description: 'Successful operation - current weather forecast returned',
		content: new OA\JsonContent(
			type: 'object',
			properties: [
				new OA\Property(property: 'temperature', type: 'number', description: 'Current temperature in Celsius'),
				new OA\Property(property: 'humidity', type: 'number', description: 'Current humidity percentage'),
				new OA\Property(property: 'description', type: 'string', description: 'Weather condition description')
			]
		)
	)]
	#[OA\Response(
		response: 400,
		description: 'Invalid request (e.g., city parameter missing)'
	)]
	#[OA\Response(
		response: 401,
		description: 'Unauthorized - API key issue'
	)]
	#[OA\Response(
		response: 404,
		description: 'City not found by WeatherAPI.com'
	)]
	#[OA\Response(
		response: 500,
		description: 'Internal server error or error communicating with WeatherAPI.com'
	)]
	public function getWeather(Request $request): JsonResponse
	{
		$city = $request->query->get('city');

		if (empty($city)) {
			return new JsonResponse(['error' => 'City parameter is required'], 400);
		}

		if (empty($this->weatherApiKey) || $this->weatherApiKey === 'YOUR_WEATHERAPI_COM_KEY') {
			$this->logger->error('Weather API key is not configured.');
			return new JsonResponse(['error' => 'Weather service is not configured.'], 500);
		}

		$apiUrl = sprintf(
			'http://api.weatherapi.com/v1/current.json?key=%s&q=%s',
			$this->weatherApiKey,
			urlencode($city)
		);

		try {
			$response = $this->httpClient->request('GET', $apiUrl);
			$statusCode = $response->getStatusCode();
			$content = $response->toArray(false);

			if ($statusCode === 200) {
				$weatherData = [
					'temperature' => $content['current']['temp_c'] ?? null,
					'humidity' => $content['current']['humidity'] ?? null,
					'description' => $content['current']['condition']['text'] ?? null,
				];
				return new JsonResponse($weatherData);
			} elseif ($statusCode === 400) {
				if (isset($content['error']['code']) && $content['error']['code'] === 1006) {
					return new JsonResponse(['error' => 'City not found by WeatherAPI.com'], 404);
				}
				$this->logger->warning('WeatherAPI.com returned 400: ' . ($content['error']['message'] ?? 'Unknown error'));
				return new JsonResponse(['error' => 'Invalid request to weather service.'], 400);
			} elseif ($statusCode === 401 || $statusCode === 403) {
				$this->logger->error('WeatherAPI.com authentication failed: ' . ($content['error']['message'] ?? 'Key issue'));
				return new JsonResponse(['error' => 'Weather service authentication failed.'], 401);
			} else {
				$this->logger->error(sprintf(
					'WeatherAPI.com request failed. Status: %s, Body: %s',
					$statusCode,
					json_encode($content)
				));
				return new JsonResponse(['error' => 'Failed to retrieve weather data.'], $statusCode);
			}
		} catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
			$this->logger->error('WeatherAPI.com transport error: ' . $e->getMessage());
			return new JsonResponse(['error' => 'Could not connect to weather service.'], 500);
		} catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
			$this->logger->error('WeatherAPI.com client error: ' . $e->getMessage());
			return new JsonResponse(['error' => 'Error processing weather service response.'], 500);
		}
	}
}
