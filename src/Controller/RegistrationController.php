<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    private $validator;
    private $mailer;
    private $router;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator, MailerInterface $mailer, UrlGeneratorInterface $router)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->validator = $validator;
        $this->mailer = $mailer;
        $this->router = $router;
    }

    #[Route(path: "/api/register", name: "api_register", methods: "POST")]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                $data['password']
            )
        );
        $user->setRoles(['ROLE_USER']);
        $user->setConfirmationToken(bin2hex(random_bytes(32)));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorsString = (string) $errors;

            return new JsonResponse(['error' => $errorsString], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // $confirmationUrl = $this->router->generate('api_confirm_email', ['token' => $user->getConfirmationToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from('no-reply@votre-domaine.com')
            ->to($user->getEmail())
            ->subject('Confirmez votre inscription')
            ->html(sprintf('Cliquez sur le lien suivant pour confirmer votre inscription : <a href="http://localhost:5173/confirmEmail/%s">Confirmer mon email</a>', $user->getConfirmationToken()));

        $this->mailer->send($email);

        return new JsonResponse(['status' => 'User created, confirmation email sent!'], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: "/api/confirm-email/{token}", name: "api_confirm_email", methods: "GET")]
    public function confirmEmail(string $token): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['confirmationToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setIsActive(true);
        $user->setConfirmationToken(null);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'User confirmed!'], JsonResponse::HTTP_OK);
    }
}
