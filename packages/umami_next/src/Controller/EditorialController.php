<?php

declare(strict_types=1);

namespace Drupal\umami_next\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\umami_next\EditorialDataBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Editorial route controller for Umami Next.
 */
final class EditorialController extends ControllerBase {

  /**
   * Constructs a new controller.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly EditorialDataBuilder $editorialData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('umami_next.editorial_data'),
    );
  }

  /**
   * Builds the recipe archive.
   */
  public function recipes(Request $request): array {
    $storage = $this->entityTypeManagerService->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', 1);

    $active_filters = [
      'category' => $request->query->get('category', ''),
      'dietary' => $request->query->get('dietary', ''),
      'cuisine' => $request->query->get('cuisine', ''),
      'sort' => $request->query->get('sort', 'date'),
    ];

    if ($active_filters['category'] && ($term = $this->editorialData->loadTermBySlug('recipe_category', $active_filters['category']))) {
      $query->condition('field_recipe_category.target_id', $term->id());
    }
    if ($active_filters['dietary'] && ($term = $this->editorialData->loadTermBySlug('dietary', $active_filters['dietary']))) {
      $query->condition('field_dietary.target_id', $term->id());
    }
    if ($active_filters['cuisine'] && ($term = $this->editorialData->loadTermBySlug('cuisine', $active_filters['cuisine']))) {
      $query->condition('field_cuisine.target_id', $term->id());
    }

    match ($active_filters['sort']) {
      'title' => $query->sort('title', 'ASC'),
      'time' => $query->sort('field_total_minutes.value', 'ASC'),
      default => $query->sort('created', 'DESC'),
    };

    $total = (int) (clone $query)->count()->execute();
    $query->pager(12);
    $ids = $query->execute();
    $nodes = $ids ? $storage->loadMultiple($ids) : [];

    return [
      '#theme' => 'umami_next_recipe_archive',
      '#cards' => array_map(fn ($node): array => $this->editorialData->buildRecipeCard($node), $nodes),
      '#filters' => [
        'category' => $this->editorialData->buildTermOptions('recipe_category'),
        'dietary' => $this->editorialData->buildTermOptions('dietary'),
        'cuisine' => $this->editorialData->buildTermOptions('cuisine'),
      ],
      '#active_filters' => $active_filters,
      '#pager' => ['#type' => 'pager'],
      '#total' => $total,
      '#cache' => [
        'tags' => ['node_list', 'taxonomy_term_list'],
        'contexts' => ['url.query_args', 'user.node_grants:view', 'user.permissions'],
      ],
    ];
  }

  /**
   * Builds the article archive.
   */
  public function stories(Request $request): array {
    $storage = $this->entityTypeManagerService->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC');

    $active_topic = $request->query->get('topic', '');
    if ($active_topic && ($term = $this->editorialData->loadTermBySlug('topic', $active_topic))) {
      $query->condition('field_topics.target_id', $term->id());
    }

    $total = (int) (clone $query)->count()->execute();
    $query->pager(9);
    $ids = $query->execute();
    $nodes = $ids ? $storage->loadMultiple($ids) : [];

    return [
      '#theme' => 'umami_next_story_archive',
      '#cards' => array_map(fn ($node): array => $this->editorialData->buildArticleCard($node), $nodes),
      '#topics' => $this->editorialData->buildTermOptions('topic', 7),
      '#active_topic' => $active_topic,
      '#pager' => ['#type' => 'pager'],
      '#total' => $total,
      '#cache' => [
        'tags' => ['node_list', 'taxonomy_term_list'],
        'contexts' => ['url.query_args', 'user.node_grants:view', 'user.permissions'],
      ],
    ];
  }

  /**
   * Builds the topic archive.
   */
  public function topic(string $slug): array {
    $topic = $this->editorialData->loadTermBySlug('topic', $slug);
    if (!$topic) {
      throw $this->createNotFoundException();
    }

    $storage = $this->entityTypeManagerService->getStorage('node');
    $recipe_query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', 1)
      ->condition('field_topics.target_id', $topic->id())
      ->sort('created', 'DESC');
    $article_query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_topics.target_id', $topic->id())
      ->sort('created', 'DESC');
    $recipe_count = (int) (clone $recipe_query)->count()->execute();
    $article_count = (int) (clone $article_query)->count()->execute();
    $recipe_ids = $recipe_query
      ->range(0, 12)
      ->execute();
    $article_ids = $article_query
      ->range(0, 3)
      ->execute();

    return [
      '#theme' => 'umami_next_topic_archive',
      '#topic' => [
        'label' => $topic->label(),
      ],
      '#recipe_cards' => array_map(fn ($node): array => $this->editorialData->buildRecipeCard($node), $recipe_ids ? $storage->loadMultiple($recipe_ids) : []),
      '#article_cards' => array_map(fn ($node): array => $this->editorialData->buildArticleCard($node), $article_ids ? $storage->loadMultiple($article_ids) : []),
      '#recipe_count' => $recipe_count,
      '#article_count' => $article_count,
      '#newsletter_form' => $this->editorialData->buildWebform('newsletter_signup'),
      '#cache' => [
        'tags' => ['node_list', 'taxonomy_term:' . $topic->id()],
        'contexts' => ['route', 'user.node_grants:view', 'user.permissions'],
      ],
    ];
  }

  /**
   * Builds the search page.
   */
  public function search(Request $request): array {
    $query = trim((string) $request->query->get('q', ''));
    $type = (string) $request->query->get('type', '');
    $result_count = $this->editorialData->countSearchResults($query, $type);
    $results = $this->editorialData->buildSearchResults($query, $type);

    return [
      '#theme' => 'umami_next_search',
      '#query' => $query,
      '#type' => $type,
      '#results' => $results,
      '#result_count' => $result_count,
      '#pager' => ['#type' => 'pager'],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['url.query_args', 'user.node_grants:view', 'user.permissions'],
      ],
    ];
  }

  /**
   * Builds the contact page.
   */
  public function contact(): array {
    return [
      '#theme' => 'umami_next_contact',
      '#contact_form' => $this->editorialData->buildWebform('contact_form'),
      '#newsletter_form' => $this->editorialData->buildWebform('newsletter_signup'),
      '#social_links' => [
        ['title' => 'Instagram', 'url' => 'https://instagram.com'],
        ['title' => 'Substack', 'url' => 'https://substack.com'],
        ['title' => 'RSS', 'url' => '/feed'],
      ],
      '#cache' => [
        'tags' => ['config:webform_list'],
      ],
    ];
  }

}
