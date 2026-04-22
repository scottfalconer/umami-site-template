<?php

declare(strict_types=1);

namespace Drupal\umami_next\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\umami_next\EditorialDataBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Builds the contact page.
   */
  public function contact(): array {
    $cacheability = (new CacheableMetadata())
      ->addCacheTags(['config:webform_list'])
      ->addCacheContexts(['user.permissions']);

    $build = [
      '#theme' => 'umami_next_contact',
      '#contact_form' => $this->editorialData->buildWebform('contact_form'),
      '#newsletter_form' => $this->editorialData->buildWebform('newsletter_signup'),
      '#social_links' => $this->editorialData->buildMenuLinks('social', $cacheability),
    ];
    $cacheability->applyTo($build);
    return $build;
  }

}
