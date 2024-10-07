<?php

namespace EcomailDeps\Wpify\Model\Interfaces;

interface ModelInterface
{
    public function refresh($object = null);
    public function model_repository();
}
