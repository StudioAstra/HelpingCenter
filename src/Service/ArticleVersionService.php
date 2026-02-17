<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleVersion;
use Doctrine\ORM\EntityManagerInterface;

class ArticleVersionService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createVersion(Article $article, string $createdBy = 'admin'): ArticleVersion
    {
        $lastVersion = $this->em->getRepository(ArticleVersion::class)
            ->findOneBy(
                ['article' => $article],
                ['versionNumber' => 'DESC']
            );

        $versionNumber = $lastVersion ? $lastVersion->getVersionNumber() + 1 : 1;

        $version = new ArticleVersion();
        $version->setArticle($article);
        $version->setTitle($article->getTitle());
        $version->setContent($article->getContent());
        $version->setVersionNumber($versionNumber);
        $version->setCreatedBy($createdBy);

        $this->em->persist($version);

        return $version;
    }
}
