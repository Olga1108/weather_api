<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
	#[Route('/subscribe-web', name: 'app_subscribe_web_page', methods: ['GET'])]
	public function subscribeWebPage(): Response
	{
		return $this->render('page/subscribe.html.twig');
	}
}
