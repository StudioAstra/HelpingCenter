<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Section;
use App\Entity\Subsection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class HelpZipImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private ArticleRenderer $renderer,
    ) {
    }

    /**
     * @return array{created_sections:int, created_subsections:int, created_articles:int, updated_articles:int}
     */
    public function importFromZip(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("L'extension PHP zip n'est pas installée.");
        }

        if (!is_file($zipPath)) {
            throw new \RuntimeException("Fichier introuvable: {$zipPath}");
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            throw new \RuntimeException("Impossible d'ouvrir le ZIP: {$zipPath}");
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || str_ends_with($name, '/')) {
                continue;
            }

            $normalized = str_replace('\\', '/', trim($name, '/'));
            $parts = array_values(array_filter(explode('/', $normalized), static fn (string $p): bool => $p !== ''));
            if (count($parts) < 2) {
                continue;
            }

            if ($this->isHiddenOrSystemPath($parts)) {
                continue;
            }

            $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if (!in_array($extension, ['md', 'markdown'], true)) {
                continue;
            }
            $entries[] = ['path' => $normalized, 'parts' => $parts];
        }

        if ($entries === []) {
            $zip->close();
            throw new \RuntimeException('Aucun fichier .md trouvé dans le ZIP.');
        }

        $commonRoot = $this->findCommonRoot($entries);
        $filesBySection = [];
        foreach ($entries as $entry) {
            $parts = $entry['parts'];
            if ($commonRoot !== null && isset($parts[0]) && $parts[0] === $commonRoot) {
                array_shift($parts);
            }

            if (count($parts) < 2) {
                continue;
            }

            $sectionDir = $this->normalizeUtf8($parts[0]);
            if ($sectionDir === '') {
                continue;
            }

            $subsectionParts = array_slice($parts, 1, -1);
            $subsectionDir = $subsectionParts !== []
                ? $this->normalizeUtf8(implode(' / ', $subsectionParts))
                : null;

            $filesBySection[$sectionDir][] = [
                'entryPath' => $entry['path'],
                'subsectionDir' => $subsectionDir !== '' ? $subsectionDir : null,
            ];
        }

        if ($filesBySection === []) {
            $zip->close();
            throw new \RuntimeException('Aucun fichier .md exploitable trouvé dans le ZIP.');
        }

        ksort($filesBySection, SORT_NATURAL | SORT_FLAG_CASE);
        $createdSections = 0;
        $createdSubsections = 0;
        $createdArticles = 0;
        $updatedArticles = 0;

        foreach ($filesBySection as $sectionDir => $items) {
            usort($items, static fn (array $a, array $b): int => strnatcasecmp($a['entryPath'], $b['entryPath']));
            $section = $this->findOrCreateSection($sectionDir, $createdSections);
            $positionByLocation = [];

            foreach ($items as $item) {
                $entryPath = $item['entryPath'];
                $subsectionDir = $item['subsectionDir'];
                $raw = $zip->getFromName($entryPath);
                if (!is_string($raw)) {
                    continue;
                }

                [$title, $markdown, $relatedHints] = $this->extractTitleAndBody($raw, $entryPath);
                if ($title === '') {
                    continue;
                }

                $subsection = null;
                if (is_string($subsectionDir) && $subsectionDir !== '') {
                    $subsection = $this->findOrCreateSubsection($section, $subsectionDir, $createdSubsections);
                }

                $slug = $this->slugger->slug($title)->lower()->toString();
                $article = $this->em->getRepository(Article::class)->findOneBy([
                    'section' => $section,
                    'subsection' => $subsection,
                    'slug' => $slug,
                ]);

                $isNew = $article === null;
                if ($isNew) {
                    $article = new Article();
                    $article->setSection($section);
                }
                $article->setSubsection($subsection);

                $content = ['markdown' => $markdown];
                if ($relatedHints !== []) {
                    $content['related'] = $relatedHints;
                }
                $article->setTitle($title);
                $article->setSlug($slug);
                $article->setContent($content);
                $article->setSearchText($this->renderer->extractText($content));

                $locationKey = $subsection?->getId() ? 'sub:' . $subsection->getId() : 'section';
                $position = $positionByLocation[$locationKey] ?? 0;
                $article->setPosition($position);
                $positionByLocation[$locationKey] = $position + 1;

                $article->setIsPublished(true);
                if (!$article->getPublishedAt()) {
                    $article->setPublishedAt(new \DateTimeImmutable());
                }

                if ($isNew) {
                    $this->em->persist($article);
                    $createdArticles++;
                } else {
                    $updatedArticles++;
                }
            }
        }

        $zip->close();
        $this->em->flush();

        return [
            'created_sections' => $createdSections,
            'created_subsections' => $createdSubsections,
            'created_articles' => $createdArticles,
            'updated_articles' => $updatedArticles,
        ];
    }

    private function findOrCreateSection(string $sectionDir, int &$createdSections): Section
    {
        $sectionDir = $this->normalizeUtf8($sectionDir);
        if ($sectionDir === '') {
            $sectionDir = 'section';
        }

        $slug = $this->slugger->slug($sectionDir)->lower()->toString();
        $section = $this->em->getRepository(Section::class)->findOneBy(['slug' => $slug]);
        if ($section) {
            return $section;
        }

        $title = trim(preg_replace('/[-_]+/', ' ', $sectionDir) ?? $sectionDir);
        if ($title === '') {
            $title = $sectionDir;
        }

        $maxPosition = (int) $this->em->createQueryBuilder()
            ->select('COALESCE(MAX(s.position), 0)')
            ->from(Section::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        $section = new Section();
        $section->setTitle($title);
        $section->setSlug($slug);
        $section->setPosition($maxPosition + 1);
        $this->em->persist($section);
        $createdSections++;

        return $section;
    }

    private function findOrCreateSubsection(Section $section, string $subsectionDir, int &$createdSubsections): Subsection
    {
        $subsectionDir = $this->normalizeUtf8($subsectionDir);
        if ($subsectionDir === '') {
            $subsectionDir = 'Sous-section';
        }

        $slug = $this->slugger->slug($subsectionDir)->lower()->toString();
        $subsection = $this->em->getRepository(Subsection::class)->findOneBy([
            'section' => $section,
            'slug' => $slug,
        ]);
        if ($subsection) {
            return $subsection;
        }

        $maxPosition = (int) $this->em->createQueryBuilder()
            ->select('COALESCE(MAX(s.position), 0)')
            ->from(Subsection::class, 's')
            ->where('s.section = :section')
            ->setParameter('section', $section)
            ->getQuery()
            ->getSingleScalarResult();

        $subsection = new Subsection();
        $subsection->setSection($section);
        $subsection->setTitle($subsectionDir);
        $subsection->setSlug($slug);
        $subsection->setPosition($maxPosition + 1);
        $this->em->persist($subsection);
        $createdSubsections++;

        return $subsection;
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function extractTitleAndBody(string $rawContent, string $entryPath): array
    {
        $content = $this->normalizeUtf8($rawContent);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = explode("\n", $content);
        $firstLine = trim((string) array_shift($lines));

        $title = ltrim($firstLine, "# \t");
        if ($title === '') {
            $filename = $this->normalizeUtf8((string) pathinfo($entryPath, PATHINFO_FILENAME));
            $title = trim(str_replace(['-', '_'], ' ', $filename));
        }
        $title = $this->normalizeUtf8($title);

        $body = implode("\n", $lines);
        $body = ltrim($body);
        $body = $this->normalizeUtf8($body);

        [$cleanBody, $relatedHints] = $this->extractSeeAlso($body);

        return [$title, $cleanBody, $relatedHints];
    }

    private function normalizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        $candidates = [
            @iconv('Windows-1252', 'UTF-8//IGNORE', $value),
            @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value),
            @iconv('UTF-8', 'UTF-8//IGNORE', $value),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            if (preg_match('//u', $candidate) === 1) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param list<array{path:string,parts:list<string>}> $entries
     */
    private function findCommonRoot(array $entries): ?string
    {
        if ($entries === []) {
            return null;
        }

        $root = $entries[0]['parts'][0] ?? null;
        if (!is_string($root) || $root === '') {
            return null;
        }

        foreach ($entries as $entry) {
            if (($entry['parts'][0] ?? null) !== $root) {
                return null;
            }
        }

        return $root;
    }

    /**
     * @param list<string> $parts
     */
    private function isHiddenOrSystemPath(array $parts): bool
    {
        foreach ($parts as $part) {
            $lower = strtolower($part);
            if ($lower === '__macosx') {
                return true;
            }

            if (str_starts_with($part, '.')) {
                return true;
            }
        }

        $filename = end($parts);
        if (!is_string($filename)) {
            return true;
        }

        if (str_starts_with($filename, '._')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function extractSeeAlso(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $out = [];
        $related = [];
        $inSeeAlso = false;
        $seeAlsoLevel = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^(#{1,6})\s*(.+)$/u', $trimmed, $matches)) {
                $level = strlen($matches[1]);
                $headingText = mb_strtolower(trim($matches[2]));

                if ($inSeeAlso && $seeAlsoLevel !== null && $level <= $seeAlsoLevel) {
                    $inSeeAlso = false;
                    $seeAlsoLevel = null;
                }

                if (!$inSeeAlso && in_array($headingText, ['voir aussi', 'voir-aussi'], true)) {
                    $inSeeAlso = true;
                    $seeAlsoLevel = $level;
                    continue;
                }
            }

            if ($inSeeAlso) {
                if (preg_match('/^\s*[-*+]\s+(.+)$/u', $trimmed, $m) || preg_match('/^\s*\d+\.\s+(.+)$/u', $trimmed, $m)) {
                    $item = trim($m[1]);
                    if (preg_match('/\[(.+?)]\([^)]+\)/u', $item, $link)) {
                        $item = trim($link[1]);
                    }

                    $item = strip_tags($item);
                    $item = preg_replace('/[*_`~]/u', '', $item) ?? $item;
                    $item = trim($item);
                    if ($item !== '') {
                        $related[] = $item;
                    }
                } elseif ($trimmed !== '') {
                    $item = strip_tags($trimmed);
                    $item = preg_replace('/[*_`~]/u', '', $item) ?? $item;
                    $item = trim($item);
                    if ($item !== '') {
                        $related[] = $item;
                    }
                }
                continue;
            }

            $out[] = $line;
        }

        $outMarkdown = trim(implode("\n", $out));
        $related = array_values(array_unique(array_filter($related, static fn (string $v): bool => trim($v) !== '')));

        return [$outMarkdown, $related];
    }
}
