<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;

#[Route('/api')]
class SubscriptionController extends AbstractController
{
	public function __construct(
		private EntityManagerInterface $entityManager,
		private ValidatorInterface $validator,
		private SubscriptionRepository $subscriptionRepository,
		private LoggerInterface $logger,
		private MailerInterface $mailer,
		private UrlGeneratorInterface $urlGenerator
	) {}

	#[Route('/subscribe', name: 'app_subscription_subscribe', methods: ['POST'])]
	#[OA\Post(
		path: '/api/subscribe',
		summary: 'Subscribe to weather updates',
		description: 'Subscribe an email to receive weather updates for a specific city with chosen frequency.',
		operationId: 'subscribe',
		tags: ['subscription'],
		requestBody: new OA\RequestBody(
			description: 'Subscription request data',
			required: true,
			content: [
				new OA\MediaType(
					mediaType: 'application/x-www-form-urlencoded',
					schema: new OA\Schema(
						type: 'object',
						required: ['email', 'city', 'frequency'],
						properties: [
							new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Email address to subscribe'),
							new OA\Property(property: 'city', type: 'string', description: 'City for weather updates'),
							new OA\Property(property: 'frequency', type: 'string', enum: ['hourly', 'daily'], description: 'Frequency of updates (hourly or daily)')
						]
					)
				),
				new OA\MediaType(
					mediaType: 'application/json',
					schema: new OA\Schema(
						type: 'object',
						required: ['email', 'city', 'frequency'],
						properties: [
							new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Email address to subscribe'),
							new OA\Property(property: 'city', type: 'string', description: 'City for weather updates'),
							new OA\Property(property: 'frequency', type: 'string', enum: ['hourly', 'daily'], description: 'Frequency of updates (hourly or daily)')
						]
					)
				)
			]
		)
	)]
	#[OA\Response(
		response: 200,
		description: 'Subscription successful. Confirmation email will be sent.'
	)]
	#[OA\Response(
		response: 400,
		description: 'Invalid input',
		content: new OA\JsonContent(
			type: 'object',
			properties: [
				new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
			]
		)
	)]
	#[OA\Response(
		response: 409,
		description: 'Email already subscribed and confirmed, or pending confirmation.'
	)]
	public function subscribe(Request $request): JsonResponse
	{
		$contentType = $request->headers->get('Content-Type');
		$data = [];

		if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
			$data['email'] = $request->request->get('email');
			$data['city'] = $request->request->get('city');
			$data['frequency'] = $request->request->get('frequency');
		} elseif (str_contains($contentType, 'application/json')) {
			$jsonData = json_decode($request->getContent(), true);
			$data['email'] = $jsonData['email'] ?? null;
			$data['city'] = $jsonData['city'] ?? null;
			$data['frequency'] = $jsonData['frequency'] ?? null;
		} else {
			return new JsonResponse(['error' => 'Unsupported Content-Type'], 415);
		}

		$existingSubscription = $this->subscriptionRepository->findOneBy(['email' => $data['email']]);
		if ($existingSubscription) {
			if ($existingSubscription->isIsConfirmed()) {
				return new JsonResponse(['error' => 'Email already subscribed and confirmed.'], 409);
			}

			return new JsonResponse(['error' => 'Email already has a pending or confirmed subscription.'], 409);
		}

		$subscription = new Subscription();
		$subscription->setEmail($data['email']);
		$subscription->setCity($data['city']);
		$subscription->setFrequency($data['frequency']);

		try {
			$subscription->setConfirmationToken(bin2hex(random_bytes(32)));
			$subscription->setUnsubscribeToken(bin2hex(random_bytes(32)));
		} catch (\Exception $e) {
			$this->logger->error('Failed to generate secure tokens: ' . $e->getMessage());

			return new JsonResponse(['error' => 'Could not process subscription due to a server error.'], 500);
		}

		$errors = $this->validator->validate($subscription);
		if (count($errors) > 0) {
			$errorMessages = [];
			foreach ($errors as $error) {
				$errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
			}
			return new JsonResponse(['errors' => $errorMessages], 400);
		}

		$this->entityManager->persist($subscription);
		$this->entityManager->flush();

		$confirmationUrl = $this->urlGenerator->generate(
			'app_subscription_confirm',
			['token' => $subscription->getConfirmationToken()],
			UrlGeneratorInterface::ABSOLUTE_URL
		);

		$emailMessage = (new Email())
			->from('no-reply@weatherapi.app')
			->to($subscription->getEmail())
			->subject('Confirm your Weather API Subscription')
			->html(
				$this->renderView(
					'emails/subscription_confirmation.html.twig',
					[
						'city' => $subscription->getCity(),
						'confirmation_url' => $confirmationUrl,
					]
				)
			);

		try {
			$this->mailer->send($emailMessage);
			$this->logger->info(sprintf('Confirmation email sent to %s for city %s', $subscription->getEmail(), $subscription->getCity()));
		} catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
			$this->logger->error(sprintf('Failed to send confirmation email to %s: %s', $subscription->getEmail(), $e->getMessage()));
		}

		$this->logger->info(sprintf('New subscription created: Email %s, City %s, Frequency %s', $data['email'], $data['city'], $data['frequency']));

		return new JsonResponse(['message' => 'Subscription successful. Please check your email to confirm.'], 200);
	}

	#[Route('/confirm/{token}', name: 'app_subscription_confirm', methods: ['GET'])]
	#[OA\Get(
		path: '/api/confirm/{token}',
		summary: 'Confirm email subscription',
		description: 'Confirms a subscription using the token sent in the confirmation email.',
		operationId: 'confirmSubscription',
		tags: ['subscription']
	)]
	#[OA\Parameter(
		name: 'token',
		in: 'path',
		description: 'Confirmation token',
		required: true,
		schema: new OA\Schema(type: 'string')
	)]
	#[OA\Response(
		response: 200,
		description: 'Subscription confirmed successfully'
	)]
	#[OA\Response(
		response: 400,
		description: 'Invalid or expired token'
	)]
	#[OA\Response(
		response: 404,
		description: 'Token not found'
	)]
	public function confirmSubscription(string $token): JsonResponse
	{
		if (empty($token)) {
			return new JsonResponse(['error' => 'Token cannot be empty.'], 400);
		}

		$subscription = $this->subscriptionRepository->findOneBy(['confirmationToken' => $token]);

		if (!$subscription) {
			return new JsonResponse(['error' => 'Token not found or already used.'], 404);
		}

		if ($subscription->isIsConfirmed()) {
			return new JsonResponse(['message' => 'Subscription already confirmed.'], 200);
		}

		$subscription->setIsConfirmed(true);
		$subscription->setConfirmationToken(null);

		$this->entityManager->persist($subscription);
		$this->entityManager->flush();

		$this->logger->info(sprintf('Subscription confirmed for email %s with token %s', $subscription->getEmail(), $token));

		return new JsonResponse(['message' => 'Subscription confirmed successfully'], 200);
	}

	#[Route('/unsubscribe/{token}', name: 'app_subscription_unsubscribe', methods: ['GET'])]
	#[OA\Get(
		path: '/api/unsubscribe/{token}',
		summary: 'Unsubscribe from weather updates',
		description: 'Unsubscribes an email from weather updates using the unsubscribe token.',
		operationId: 'unsubscribe',
		tags: ['subscription']
	)]
	#[OA\Parameter(
		name: 'token',
		in: 'path',
		description: 'Unsubscribe token',
		required: true,
		schema: new OA\Schema(type: 'string')
	)]
	#[OA\Response(
		response: 200,
		description: 'Unsubscribed successfully'
	)]
	#[OA\Response(
		response: 400,
		description: 'Invalid token'
	)]
	#[OA\Response(
		response: 404,
		description: 'Token not found'
	)]
	public function unsubscribe(string $token): JsonResponse
	{
		if (empty($token)) {
			return new JsonResponse(['error' => 'Token cannot be empty.'], 400);
		}

		$subscription = $this->subscriptionRepository->findOneBy(['unsubscribeToken' => $token]);

		if (!$subscription) {
			return new JsonResponse(['error' => 'Token not found or subscription already removed.'], 404);
		}

		$this->entityManager->remove($subscription);
		$this->entityManager->flush();

		$this->logger->info(sprintf('Subscription removed for email %s with unsubscribe token %s', $subscription->getEmail(), $token));

		return new JsonResponse(['message' => 'Unsubscribed successfully'], 200);
	}
}
