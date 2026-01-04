<?php

namespace splitbrain\notmore\Controller;

class HomeController extends AbstractController
{
    public function index(array $data = []): string
    {
        return $this->render('home/index.html.twig');
    }
}
