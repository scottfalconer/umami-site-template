<?php

declare(strict_types=1);

namespace Drupal\umami_next\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps utility Canvas pages out of public content search results.
 */
final class SearchIndexSubscriber implements EventSubscriberInterface {

  /**
   * Removes non-discovery Canvas pages from the content search index.
   */
  public function excludeUtilityCanvasPages(IndexingItemsEvent $event): void {
    if ($event->getIndex()->id() !== 'content') {
      return;
    }

    $items = $event->getItems();
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if (!$object instanceof ContentEntityInterface || $object->getEntityTypeId() !== 'canvas_page') {
        continue;
      }

      $path = $object->hasField('path') ? $object->get('path')->getValue() : [];
      if (($path[0]['alias'] ?? NULL) === '/404') {
        unset($items[$item_id]);
      }
    }

    $event->setItems($items);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::INDEXING_ITEMS => 'excludeUtilityCanvasPages',
    ];
  }

}
