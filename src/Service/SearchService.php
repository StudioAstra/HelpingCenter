<?php

namespace App\Service;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

class SearchService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return Article[]
     */
    public function search(string $query, int $limit = 20): array
    {
        if (empty(trim($query))) {
            return [];
        }

        $qb = $this->em->createQueryBuilder();

        return $qb->select('a')
            ->from(Article::class, 'a')
            ->join('a.subsection', 's')
            ->join('s.section', 'sec')
            ->where('a.isPublished = :published')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(a.title)', ':query'),
                    $qb->expr()->like('LOWER(a.searchText)', ':query'),
                    $qb->expr()->like('LOWER(s.title)', ':query'),
                    $qb->expr()->like('LOWER(sec.title)', ':query')
                )
            )
            ->setParameter('published', true)
            ->setParameter('query', '%' . mb_strtolower(trim($query)) . '%')
            ->orderBy('a.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
