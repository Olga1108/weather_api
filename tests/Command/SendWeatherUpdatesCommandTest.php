<?php

namespace App\Tests\Command;

use App\Command\SendWeatherUpdatesCommand;
use App\Repository\SubscriptionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendWeatherUpdatesCommandTest extends KernelTestCase
{
	/** @var SubscriptionRepository&MockObject */
	private MockObject $subscriptionRepository;

	/** @var HttpClientInterface&MockObject */
	private MockObject $httpClient;

	/** @var MailerInterface&MockObject */
	private MockObject $mailer;

	/** @var LoggerInterface&MockObject */
	private MockObject $logger;

	private CommandTester $commandTester;

	protected function setUp(): void
	{
		parent::setUp();

		self::bootKernel();
		$application = new Application(self::$kernel);

		$this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
		$this->httpClient = $this->createMock(HttpClientInterface::class);
		$this->mailer = $this->createMock(MailerInterface::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$command = new SendWeatherUpdatesCommand(
			$this->subscriptionRepository,
			$this->httpClient,
			$this->mailer,
			'test_weather_api_key'
		);
		$application->add($command);

		$commandFromApp = $application->find('app:send-weather-updates');
		$this->commandTester = new CommandTester($commandFromApp);
	}

	public function testExecuteNoSubscriptionsFound(): void
	{
		$this->subscriptionRepository->expects($this->once())
			->method('findBy')
			->with(['isConfirmed' => true, 'frequency' => 'hourly'])
			->willReturn([]);

		$this->httpClient->expects($this->never())
			->method('request');

		$this->mailer->expects($this->never())
			->method('send');

		$this->commandTester->execute([
			'frequency' => 'hourly',
		]);

		$output = $this->commandTester->getDisplay();
		$this->commandTester->assertCommandIsSuccessful();
		$this->assertStringContainsString('No confirmed subscriptions found for frequency: hourly', $output);
	}

	public function testExecuteSuccessfullySendsEmail(): void
	{
		$subscription = $this->createMock(\App\Entity\Subscription::class);
		$subscription->method('getEmail')->willReturn('test@example.com');
		$subscription->method('getCity')->willReturn('London');

		$this->subscriptionRepository->expects($this->once())
			->method('findBy')
			->with(['isConfirmed' => true, 'frequency' => 'hourly'])
			->willReturn([$subscription]);

		$mockWeatherResponse = $this->createMock(ResponseInterface::class);
		$mockWeatherResponse->method('toArray')->willReturn([
			'current' => [
				'temp_c' => 15,
				'humidity' => 70,
				'condition' => ['text' => 'Partly cloudy'],
			],
		]);

		$this->httpClient->expects($this->once())
			->method('request')
			->with('GET', 'https://api.weatherapi.com/v1/current.json', [
				'query' => [
					'key' => 'test_weather_api_key',
					'q' => 'London',
				],
			])
			->willReturn($mockWeatherResponse);

		$this->mailer->expects($this->once())
			->method('send')
			->with($this->callback(function ($email) {
				/** @var \Symfony\Component\Mime\Email $email */
				$this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $email);
				$this->assertEquals('no-reply@weatherapi.app', $email->getFrom()[0]->getAddress());
				$this->assertEquals('test@example.com', $email->getTo()[0]->getAddress());
				$this->assertEquals('Weather Update for London', $email->getSubject());
				$this->assertStringContainsString('Temperature: 15°C', $email->getTextBody());
				$this->assertStringContainsString('Humidity: 70%', $email->getTextBody());
				$this->assertStringContainsString('Condition: Partly cloudy', $email->getTextBody());
				return true;
			}));

		$this->commandTester->execute([
			'frequency' => 'hourly',
		]);

		$output = $this->commandTester->getDisplay();
		$this->commandTester->assertCommandIsSuccessful();
		$this->assertStringContainsString('✅ Email sent to test@example.com for city London', $output);
	}
}
