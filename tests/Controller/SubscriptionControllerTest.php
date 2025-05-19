<?php

namespace App\Tests\Controller;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionControllerTest extends WebTestCase
{
	private ?EntityManagerInterface $entityManager = null;
	private ?SubscriptionRepository $subscriptionRepository = null;
	private $client;

	protected function setUp(): void
	{
		// $this->markTestSkipped('Temporarily skipping SubscriptionControllerTest due to persistent DB issues.');


		parent::setUp();
		sleep(2);

		$this->client = static::createClient(['environment' => 'test', 'debug' => true]); // Explicitly set environment
		$this->entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
		$this->subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
	}

	public function testSubscribeAndConfirmSuccess(): void
	{
		$testEmail = 'test-' . uniqid() . '@example.com';

		$this->client->request(
			'POST',
			'/api/subscribe',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			json_encode([
				'email' => $testEmail,
				'city' => 'TestCity',
				'frequency' => 'hourly'
			])
		);

		$this->assertResponseIsSuccessful();
		$this->assertResponseStatusCodeSame(Response::HTTP_OK);

		$responseContent = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertEquals('Subscription successful. Please check your email to confirm.', $responseContent['message']);

		/** @var Subscription $subscription */
		$subscription = $this->subscriptionRepository->findOneBy(['email' => $testEmail]);
		$this->assertNotNull($subscription, 'Subscription should exist in DB after subscribe request.');
		$this->assertEquals('TestCity', $subscription->getCity());
		$this->assertEquals('hourly', $subscription->getFrequency());
		$this->assertFalse($subscription->isIsConfirmed(), 'Subscription should not be confirmed yet.');
		$this->assertNotNull($subscription->getConfirmationToken(), 'Confirmation token should be set.');
		$confirmationToken = $subscription->getConfirmationToken();

		$this->client->request('GET', '/api/confirm/' . $confirmationToken);
		$this->assertResponseIsSuccessful();
		$this->assertResponseStatusCodeSame(Response::HTTP_OK);

		$responseContentConfirm = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertEquals('Subscription confirmed successfully', $responseContentConfirm['message']);

		$subscription = $this->subscriptionRepository->findOneBy(['email' => $testEmail]);
		$this->assertTrue($subscription->isIsConfirmed(), 'Subscription should be confirmed.');
		$this->assertNull($subscription->getConfirmationToken(), 'Confirmation token should be nullified after confirmation.');
	}

	public function testSubscribeEmailAlreadyExistsConfirmed(): void
	{
		$testEmail = 'confirmed-' . uniqid() . '@example.com';

		$existingSubscription = new Subscription();
		$existingSubscription->setEmail($testEmail);
		$existingSubscription->setCity('ExistingCity');
		$existingSubscription->setFrequency('daily');
		$existingSubscription->setIsConfirmed(true);
		$this->entityManager->persist($existingSubscription);
		$this->entityManager->flush();

		$this->client->request(
			'POST',
			'/api/subscribe',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			json_encode([
				'email' => $testEmail,
				'city' => 'NewCity',
				'frequency' => 'hourly'
			])
		);

		$this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

		$responseContent = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertEquals('Email already subscribed and confirmed.', $responseContent['error']);
	}

	public function testUnsubscribeSuccess(): void
	{
		$testEmail = 'unsubscribe-' . uniqid() . '@example.com';
		$unsubscribeToken = bin2hex(random_bytes(16));

		$subscription = new Subscription();
		$subscription->setEmail($testEmail);
		$subscription->setCity('CityToUnsubscribe');
		$subscription->setFrequency('daily');
		$subscription->setIsConfirmed(true);
		$subscription->setUnsubscribeToken($unsubscribeToken);

		$this->entityManager->persist($subscription);
		$this->entityManager->flush();

		$subscriptionId = $subscription->getId();

		$this->client->request('GET', '/api/unsubscribe/' . $unsubscribeToken);

		$this->assertResponseIsSuccessful();
		$this->assertResponseStatusCodeSame(Response::HTTP_OK);

		$responseContent = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertEquals('Unsubscribed successfully', $responseContent['message']);

		$deletedSubscription = $this->subscriptionRepository->find($subscriptionId);
		$this->assertNull($deletedSubscription, 'Subscription should be deleted from DB after unsubscribe.');
	}

	public function testUnsubscribeInvalidToken(): void
	{
		$this->client->request('GET', '/api/unsubscribe/invalid-token-value');

		$this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

		$responseContent = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertEquals('Token not found or subscription already removed.', $responseContent['error']);
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		if ($this->entityManager !== null) {
			$this->entityManager->clear();
			$this->entityManager = null;
		}

		$this->subscriptionRepository = null;
		$this->client = null;
	}
}
