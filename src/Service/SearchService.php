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
        $normalized = trim($query);
        if ($normalized === '') {
            return [];
        }

        $results = $this->searchByPhrase($normalized, $limit);
        if ($results !== []) {
            return $results;
        }

        $tokens = $this->tokenize($normalized);
        if (count($tokens) <= 1) {
            return [];
        }

        return $this->searchByTokens($tokens, $limit);
    }

    public function suggestQuery(string $query): ?string
    {
        $query = trim(mb_strtolower($query));
        if ($query === '' || mb_strlen($query) < 3) {
            return null;
        }

        $articles = $this->em->getRepository(Article::class)->findBy(['isPublished' => true], ['updatedAt' => 'DESC'], 300);

        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ($articles as $article) {
            $title = trim((string) $article->getTitle());
            if ($title === '') {
                continue;
            }

            $normalizedTitle = mb_strtolower($title);
            $distance = levenshtein($this->ascii($query), $this->ascii($normalizedTitle));
            if ($distance < $bestScore) {
                $bestScore = $distance;
                $best = $title;
            }
        }

        if ($best === null || $bestScore > 5) {
            return null;
        }

        return $best;
    }

    /**
     * @return Article[]
     */
    private function searchByPhrase(string $query, int $limit): array
    {
        $qb = $this->em->createQueryBuilder();

        return $qb->select('a')
            ->from(Article::class, 'a')
            ->join('a.section', 'sec')
            ->leftJoin('a.subsection', 's')
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

    /**
     * @param list<string> $tokens
     * @return Article[]
     */
    private function searchByTokens(array $tokens, int $limit): array
    {
        $qb = $this->em->createQueryBuilder();
        $expr = $qb->expr()->orX();

        foreach ($tokens as $index => $token) {
            $param = 'q' . $index;
            $expr->add($qb->expr()->like('LOWER(a.title)', ':' . $param));
            $expr->add($qb->expr()->like('LOWER(a.searchText)', ':' . $param));
            $expr->add($qb->expr()->like('LOWER(s.title)', ':' . $param));
            $expr->add($qb->expr()->like('LOWER(sec.title)', ':' . $param));
            $qb->setParameter($param, '%' . $token . '%');
        }

        $query = $qb->select('a')
            ->from(Article::class, 'a')
            ->join('a.section', 'sec')
            ->leftJoin('a.subsection', 's')
            ->where('a.isPublished = :published')
            ->andWhere($expr)
            ->setParameter('published', true)
            ->orderBy('a.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $query): array
    {
        $query = mb_strtolower(trim($query));
        $parts = preg_split('/\s+/', $query) ?: [];
        $tokens = array_values(array_filter(array_map(static fn (string $s): string => trim($s), $parts), static fn (string $s): bool => mb_strlen($s) >= 2));

        return array_values(array_unique($tokens));
    }

    private function ascii(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return is_string($ascii) ? $ascii : $value;
    }
}
