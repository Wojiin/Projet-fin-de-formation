<?php

namespace App\Controller;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function __invoke(
        Request $request,
        RefreshTokenRepository $repository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $tokenValue = $request->cookies->get('refresh_token');
        if (is_string($tokenValue) && '' !== $tokenValue) {
            $token = $repository->findOneBy(['refreshToken' => $tokenValue]);
            if (null !== $token) {
                $entityManager->remove($token);
                $entityManager->flush();
            }
        }

        $response = new JsonResponse(['message' => 'Déconnexion réussie.']);
        $response->headers->setCookie(Cookie::create('refresh_token')
            ->withValue('')
            ->withExpires(new \DateTimeImmutable('-1 hour'))
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT));

        return $response;
    }
}
