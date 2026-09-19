<?php
namespace App\Interfaces;

interface Listable {
    public function getAll(array $filters, int $page, int $perPage): array;
    public function countAll(array $filters): int;
}