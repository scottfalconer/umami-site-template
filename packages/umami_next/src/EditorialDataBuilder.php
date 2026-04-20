<?php

declare(strict_types=1);

namespace Drupal\umami_next;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\image\ImageStyleInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Builds editorial data structures for routes, blocks, and theme templates.
 */
final class EditorialDataBuilder {

  /**
   * Image style variants used by the theme templates.
   *
   * @var array<string, string>
   */
  private const IMAGE_STYLES = [
    'avatar' => '1_1_300x300_focal_point_webp',
    'card' => '4_3_500x375_focal_point_webp',
    'hero' => '3_4_708x944_focal_point_webp',
    'search' => '1_1_300x300_focal_point_webp',
    'split' => '3_4_708x944_focal_point_webp',
  ];

  /**
   * Loaded image styles keyed by machine name.
   *
   * @var array<string, \Drupal\image\ImageStyleInterface|null>
   */
  private array $imageStyles = [];

  /**
   * Creates a new editorial data builder.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Loads featured nodes.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Featured nodes.
   */
  public function loadFeaturedNodes(string $bundle, int $limit): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->condition('promote', 1)
      ->sort('sticky', 'DESC')
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();

    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  /**
   * Loads the top featured node for a bundle.
   */
  public function loadFeaturedNode(string $bundle): ?NodeInterface {
    return $this->loadFeaturedNodes($bundle, 1)[0] ?? NULL;
  }

  /**
   * Loads latest cards for a bundle.
   */
  public function loadLatestCards(string $bundle, int $limit): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();

    $cards = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $cards[] = $bundle === 'article'
        ? $this->buildArticleCard($node)
        : $this->buildRecipeCard($node);
    }
    return $cards;
  }

  /**
   * Loads specific recipe cards by UUID order.
   *
   * @param string[] $uuids
   *   Node UUIDs.
   *
   * @return array<int, array<string, mixed>>
   *   Recipe cards.
   */
  public function loadRecipeCardsByUuids(array $uuids): array {
    $cards = [];
    foreach ($this->loadNodesByUuids($uuids) as $node) {
      if ($node instanceof NodeInterface && $node->bundle() === 'recipe') {
        $cards[] = $this->buildRecipeCard($node);
      }
    }
    return $cards;
  }

  /**
   * Loads taxonomy terms by vocabulary.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Terms.
   */
  public function loadTerms(string $vocabulary, int $limit = 0): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vocabulary)
      ->sort('weight')
      ->sort('name');
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  /**
   * Builds simple term options.
   */
  public function buildTermOptions(string $vocabulary, int $limit = 0): array {
    $options = [];
    foreach ($this->loadTerms($vocabulary, $limit) as $term) {
      $options[] = [
        'label' => $term->label(),
        'slug' => (string) $term->get('field_slug')->value,
      ];
    }
    return $options;
  }

  /**
   * Loads a taxonomy term by custom slug.
   */
  public function loadTermBySlug(string $vocabulary, string $slug): ?TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vocabulary)
      ->condition('field_slug', $slug)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $term = $storage->load(reset($ids));
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Counts usage of a topic term.
   */
  public function countTopicUsage(TermInterface $term): int {
    $storage = $this->entityTypeManager->getStorage('node');
    return (int) $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', ['recipe', 'article'], 'IN')
      ->condition('status', 1)
      ->condition('field_topics.target_id', $term->id())
      ->count()
      ->execute();
  }

  /**
   * Builds topic tiles for the homepage.
   */
  public function buildTopicTiles(int $limit = 6): array {
    $topics = [];
    foreach ($this->loadTerms('topic', $limit) as $term) {
      $topics[] = [
        'label' => $term->label(),
        'count' => $this->countTopicUsage($term),
        'url' => Url::fromRoute('umami_next.topic', [
          'slug' => (string) $term->get('field_slug')->value,
        ])->toString(),
      ];
    }
    return $topics;
  }

  /**
   * Builds the site-wide editorial promo link.
   *
   * @return array<string, mixed>
   *   Promo link data.
   */
  public function buildPromoLink(): array {
    $collection = $this->loadFeaturedNode('collection');
    if (!$collection) {
      return [
        'eyebrow' => new TranslatableMarkup('New this week'),
        'title' => new TranslatableMarkup('Browse the latest collections'),
        'url' => Url::fromRoute('umami_next.recipes')->toString(),
      ];
    }

    return [
      'eyebrow' => new TranslatableMarkup('New this week'),
      'title' => $collection->label(),
      'url' => $collection->toUrl()->toString(),
    ];
  }

  /**
   * Builds article card data.
   */
  public function buildArticleCard(NodeInterface $node, bool $featured = FALSE): array {
    $topic = '';
    if ($node->hasField('field_topics') && !$node->get('field_topics')->isEmpty()) {
      $topic = $node->get('field_topics')->first()?->entity?->label() ?? '';
    }

    return [
      'title' => $node->label(),
      'summary' => $this->fieldString($node, 'field_description'),
      'url' => $node->toUrl()->toString(),
      'image' => $this->buildImageData($node, $featured ? 'hero' : 'card'),
      'author' => $this->buildAuthor($node->getOwner()),
      'date' => $this->dateFormatter->format($node->getCreatedTime(), 'custom', 'j M Y'),
      'topic' => $topic,
      'featured' => $featured,
    ];
  }

  /**
   * Builds recipe card data.
   */
  public function buildRecipeCard(NodeInterface $node): array {
    return [
      'title' => $node->label(),
      'summary' => $this->fieldString($node, 'field_description'),
      'url' => $node->toUrl()->toString(),
      'image' => $this->buildImageData($node, 'card'),
      'category' => $node->hasField('field_recipe_category') ? $node->get('field_recipe_category')->entity?->label() ?? '' : '',
      'cuisine' => $node->hasField('field_cuisine') ? $node->get('field_cuisine')->entity?->label() ?? '' : '',
      'total' => $node->hasField('field_total_minutes') ? (int) $node->get('field_total_minutes')->value : 0,
      'difficulty' => $node->hasField('field_difficulty') ? (string) $node->get('field_difficulty')->value : '',
      'servings' => $node->hasField('field_servings') ? (int) $node->get('field_servings')->value : 0,
    ];
  }

  /**
   * Builds collection card data.
   */
  public function buildCollectionCard(NodeInterface $node): array {
    return [
      'title' => $node->label(),
      'summary' => $this->fieldString($node, 'field_description'),
      'url' => $node->toUrl()->toString(),
      'image' => $this->buildImageData($node, 'split'),
      'intro' => strip_tags($this->fieldString($node, 'field_content')),
    ];
  }

  /**
   * Builds image data from a featured image field.
   */
  public function buildImageData(NodeInterface $node, string $variant = 'card'): array {
    if (!$node->hasField('field_featured_image') || $node->get('field_featured_image')->isEmpty()) {
      return ['url' => '', 'alt' => $node->label()];
    }
    $media = $node->get('field_featured_image')->entity;
    if (!$media || !$media->hasField('field_media_image')) {
      return ['url' => '', 'alt' => $node->label()];
    }
    $image = $media->get('field_media_image')->first();
    if (!$image || !$image->entity) {
      return ['url' => '', 'alt' => $node->label()];
    }
    return [
      'url' => $this->buildStyledImageUrl($image->entity->getFileUri(), $variant),
      'alt' => $image->alt ?: $node->label(),
    ];
  }

  /**
   * Builds author data for cards and detail pages.
   */
  public function buildAuthor(?UserInterface $user): ?array {
    if (!$user) {
      return NULL;
    }
    $picture = $user->hasField('user_picture') ? $user->get('user_picture')->entity : NULL;
    return [
      'name' => $user->getDisplayName(),
      'role' => $user->hasField('field_role') ? (string) $user->get('field_role')->value : '',
      'bio' => $user->hasField('field_bio') ? (string) $user->get('field_bio')->value : '',
      'image' => $picture ? $this->buildStyledImageUrl($picture->getFileUri(), 'avatar') : '',
    ];
  }

  /**
   * Builds masthead cards from active author accounts.
   *
   * @return array<int, array<string, string>>
   *   Author cards.
   */
  public function buildAuthorCards(): array {
    $storage = $this->entityTypeManager->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('name', 'ASC')
      ->execute();

    $cards = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $author = $this->buildAuthor($user);
      if (!$author) {
        continue;
      }
      if ($user->id() === 1 || ($author['role'] === '' && $author['bio'] === '')) {
        continue;
      }
      $cards[] = $author;
    }
    return $cards;
  }

  /**
   * Builds a webform render array by machine name.
   */
  public function buildWebform(string $id): array {
    if (!$this->entityTypeManager->hasDefinition('webform')) {
      return [];
    }

    $webform = $this->entityTypeManager->getStorage('webform')->load($id);
    if (!$webform) {
      return [];
    }

    return [
      '#type' => 'webform',
      '#webform' => $webform->id(),
    ];
  }

  /**
   * Builds search results from supported node bundles.
   *
   * @return array<int, array<string, mixed>>
   *   Search results.
   */
  public function countSearchResults(string $query, string $type): int {
    return (int) $this->buildSearchQuery($query, $type)
      ->count()
      ->execute();
  }

  /**
   * Builds search results from supported node bundles.
   *
   * @return array<int, array<string, mixed>>
   *   Search results.
   */
  public function buildSearchResults(string $query, string $type, int $page_size = 12): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $this->buildSearchQuery($query, $type)
      ->sort('created', 'DESC')
      ->pager($page_size)
      ->execute();

    if (!$ids) {
      return [];
    }

    $nodes = $storage->loadMultiple($ids);
    $results = [];
    $needle = mb_strtolower($query);
    foreach ($ids as $id) {
      $node = $nodes[$id] ?? NULL;
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $summary = $this->fieldString($node, 'field_description');
      $title = $node->label();
      $results[] = [
        'type' => $node->bundle(),
        'title' => $title,
        'summary' => $summary,
        'url' => $node->toUrl()->toString(),
        'image' => $this->buildImageData($node, 'search'),
        '_score' => $needle === '' ? [] : [
          str_starts_with(mb_strtolower($title), $needle) ? 3 : 0,
          str_contains(mb_strtolower($title), $needle) ? 2 : 0,
          str_contains(mb_strtolower($summary), $needle) ? 1 : 0,
          match ($node->bundle()) {
            'article' => 3,
            'collection' => 2,
            default => 1,
          },
          $node->getCreatedTime(),
        ],
      ];
    }

    if ($needle !== '') {
      usort($results, static function (array $a, array $b): int {
        foreach ($a['_score'] as $index => $score) {
          $comparison = ($b['_score'][$index] ?? 0) <=> $score;
          if ($comparison !== 0) {
            return $comparison;
          }
        }
        return 0;
      });
    }

    foreach ($results as &$result) {
      unset($result['_score']);
    }
    unset($result);

    return $results;
  }

  /**
   * Builds an image style URL with an original-file fallback.
   */
  private function buildStyledImageUrl(string $uri, string $variant): string {
    $style_name = self::IMAGE_STYLES[$variant] ?? self::IMAGE_STYLES['card'];
    $style = $this->loadImageStyle($style_name);
    if ($style instanceof ImageStyleInterface) {
      return $style->buildUrl($uri);
    }
    return $this->fileUrlGenerator->generateString($uri);
  }

  /**
   * Loads an image style by machine name.
   */
  private function loadImageStyle(string $style_name): ?ImageStyleInterface {
    if (!array_key_exists($style_name, $this->imageStyles)) {
      $style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
      $this->imageStyles[$style_name] = $style instanceof ImageStyleInterface ? $style : NULL;
    }
    return $this->imageStyles[$style_name];
  }

  /**
   * Builds recipe cards from a node reference field.
   */
  public function buildReferencedRecipeCards(NodeInterface $node, string $field_name): array {
    return $this->buildReferencedCards($node, $field_name, fn (NodeInterface $item): array => $this->buildRecipeCard($item));
  }

  /**
   * Builds article cards from a node reference field.
   */
  public function buildReferencedArticleCards(NodeInterface $node, string $field_name): array {
    return $this->buildReferencedCards($node, $field_name, fn (NodeInterface $item): array => $this->buildArticleCard($item));
  }

  /**
   * Builds related article cards based on shared topics.
   *
   * @return array<int, array<string, mixed>>
   *   Related article cards.
   */
  public function buildRelatedArticleCards(NodeInterface $node, int $limit = 2): array {
    if (!$node->hasField('field_topics') || $node->get('field_topics')->isEmpty()) {
      return [];
    }

    $topic_ids = array_map(fn ($item): int => (int) $item->target_id, iterator_to_array($node->get('field_topics')));
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('nid', $node->id(), '<>')
      ->condition('field_topics.target_id', $topic_ids, 'IN')
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();

    $cards = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $related) {
      if ($related instanceof NodeInterface) {
        $cards[] = $this->buildArticleCard($related);
      }
    }
    return $cards;
  }

  /**
   * Loads nodes referenced by a node field and maps them into cards.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that owns the reference field.
   * @param string $field_name
   *   The machine name of the node reference field.
   * @param callable $mapper
   *   A mapper that converts a referenced node into card data.
   *
   * @return array<int, array<string, mixed>>
   *   Mapped card data.
   */
  private function buildReferencedCards(NodeInterface $node, string $field_name, callable $mapper): array {
    if (!$node->hasField($field_name)) {
      return [];
    }

    $cards = [];
    foreach ($node->get($field_name)->referencedEntities() as $referenced) {
      if (!$referenced instanceof NodeInterface) {
        continue;
      }
      if (!$referenced->isPublished() || !$referenced->access('view')) {
        continue;
      }
      $cards[] = $mapper($referenced);
    }
    return $cards;
  }

  /**
   * Returns a field string value when present.
   */
  private function fieldString(NodeInterface $node, string $field_name): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }
    return (string) $node->get($field_name)->value;
  }

  /**
   * Builds a query for the supported content search bundles.
   */
  private function buildSearchQuery(string $query, string $type): QueryInterface {
    $bundles = match ($type) {
      'recipe' => ['recipe'],
      'article' => ['article'],
      'collection' => ['collection'],
      default => ['recipe', 'article', 'collection'],
    };

    $query_builder = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundles, 'IN')
      ->condition('status', 1);

    if ($query !== '') {
      $query_builder->condition(
        $query_builder->orConditionGroup()
          ->condition('title', $query, 'CONTAINS')
          ->condition('field_description', $query, 'CONTAINS')
          ->condition('field_content', $query, 'CONTAINS')
      );
    }

    return $query_builder;
  }

  /**
   * Loads nodes by ordered UUIDs.
   *
   * @param string[] $uuids
   *   Node UUIDs.
   *
   * @return array<int, \Drupal\node\NodeInterface>
   *   Loaded nodes.
   */
  private function loadNodesByUuids(array $uuids): array {
    if (!$uuids) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuids, 'IN')
      ->condition('status', 1)
      ->execute();

    $nodes_by_uuid = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $node) {
      if ($node instanceof NodeInterface && $node->access('view')) {
        $nodes_by_uuid[$node->uuid()] = $node;
      }
    }

    $nodes = [];
    foreach ($uuids as $uuid) {
      if (isset($nodes_by_uuid[$uuid])) {
        $nodes[] = $nodes_by_uuid[$uuid];
      }
    }
    return $nodes;
  }

}
