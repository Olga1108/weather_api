<?php

namespace App\Command;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
	name: 'app:send-weather-updates',
	description: 'Send weather updates to subscribed users based on their frequency.',
)]
class SendWeatherUpdatesCommand extends Command
{
	public function __construct(
		private readonly SubscriptionRepository $subscriptionRepository,
		private readonly HttpClientInterface $httpClient,
		private readonly MailerInterface $mailer,
		private readonly string $weatherApiKey,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('frequency', InputArgument::REQUIRED, 'Frequency of updates (hourly or daily)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$frequency = $input->getArgument('frequency');

		if (!in_array($frequency, ['hourly', 'daily'], true)) {
			$output->writeln('<error>Invalid frequency. Use "hourly" or "daily".</error>');
			return Command::FAILURE;
		}

		$subscriptions = $this->subscriptionRepository->findBy([
			'isConfirmed' => true,
			'frequency' => $frequency
		]);

		if (empty($subscriptions)) {
			$output->writeln(sprintf('<info>No confirmed subscriptions found for frequency: %s</info>', $frequency));
			return Command::SUCCESS; // Still a success, just nothing to do
		}

		$groupedByCity = [];

		foreach ($subscriptions as $subscription) {
			$groupedByCity[$subscription->getCity()][] = $subscription;
		}

		foreach ($groupedByCity as $city => $subs) {
			$weatherData = $this->fetchWeather($city, $output);
			if (!$weatherData) {
				$output->writeln("<error>Failed to fetch weather for city: $city</error>");
				continue;
			}

			foreach ($subs as $sub) {
				$this->sendWeatherEmail($sub, $weatherData);
				$output->writeln("✅ Email sent to {$sub->getEmail()} for city {$city}");
			}
		}

		return Command::SUCCESS;
	}

	private function fetchWeather(string $city, OutputInterface $output): ?array
	{
		try {
			$response = $this->httpClient->request('GET', 'https://api.weatherapi.com/v1/current.json', [
				'query' => [
					'key' => $this->weatherApiKey,
					'q' => $city,
				],
			]);

			$data = $response->toArray();

			return [
				'temperature' => $data['current']['temp_c'],
				'humidity' => $data['current']['humidity'],
				'description' => $data['current']['condition']['text'],
			];
		} catch (\Throwable $e) {
			// It might be good to log this error
			$output->writeln(sprintf('<error>Error fetching weather for %s: %s</error>', $city, $e->getMessage()));
			return null;
		}
	}

	private function sendWeatherEmail(Subscription $subscription, array $weather): void
	{
		$email = (new Email())
			->from('no-reply@weatherapi.app')
			->to($subscription->getEmail())
			->subject("Weather Update for {$subscription->getCity()}")
			->text(sprintf(
				"Hello!\n\nHere is the latest weather for %s:\n\nTemperature: %s°C\nHumidity: %s%%\nCondition: %s\n\nThanks for subscribing!",
				$subscription->getCity(),
				$weather['temperature'],
				$weather['humidity'],
				$weather['description']
			));

		$this->mailer->send($email);
	}
}
