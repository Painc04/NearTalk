<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MensajesController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }
}
