<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article')]
#[ORM\UniqueConstraint(name: 'UNIQ_ARTICLE_SUBSECTION_SLUG', columns: ['subsection_id', 'slug'])]
#[ORM\Index(columns: ['search_text'], flags: ['fulltext'])]
#[ORM\HasLifecycleCallbacks]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subsection::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subsection $subsection = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $searchText = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @var Collection<int, ArticleVersion>
     */
    #[ORM\OneToMany(targetEntity: ArticleVersion::class, mappedBy: 'article', cascade: ['persist'], orphanRemoval: true)]
    private Collection $versions;

    /**
     * @var Collection<int, ArticleFeedback>
     */
    #[ORM\OneToMany(targetEntity: ArticleFeedback::class, mappedBy: 'article', cascade: ['remove'], orphanRemoval: true)]
    private Collection $feedbacks;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->feedbacks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubsection(): ?Subsection
    {
        return $this->subsection;
    }

    public function setSubsection(?Subsection $subsection): static
    {
        $this->subsection = $subsection;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getContent(): ?array
    {
        return $this->content;
    }

    public function setContent(?array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getSearchText(): ?string
    {
        return $this->searchText;
    }

    public function setSearchText(?string $searchText): static
    {
        $this->searchText = $searchText;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /**
     * @return Collection<int, ArticleVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(ArticleVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setArticle($this);
        }

        return $this;
    }

    public function removeVersion(ArticleVersion $version): static
    {
        if ($this->versions->removeElement($version)) {
            // set the owning side to null (unless already changed)
            if ($version->getArticle() === $this) {
                $version->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleFeedback>
     */
    public function getFeedbacks(): Collection
    {
        return $this->feedbacks;
    }

    public function addFeedback(ArticleFeedback $feedback): static
    {
        if (!$this->feedbacks->contains($feedback)) {
            $this->feedbacks->add($feedback);
            $feedback->setArticle($this);
        }

        return $this;
    }

    public function removeFeedback(ArticleFeedback $feedback): static
    {
        if ($this->feedbacks->removeElement($feedback)) {
            // set the owning side to null (unless already changed)
            if ($feedback->getArticle() === $this) {
                $feedback->setArticle(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
