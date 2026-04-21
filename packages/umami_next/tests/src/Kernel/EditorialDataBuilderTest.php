<?php

declare(strict_types=1);

namespace Drupal\Tests\umami_next\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;
use Drupal\umami_next\EditorialDataBuilder;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers editorial helper behavior that should stay Drupal-native.
 *
 * @group umami_next
 */
#[RunTestsInSeparateProcesses]
final class EditorialDataBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'image',
    'link',
    'media',
    'menu_link_content',
    'node',
    'options',
    'system',
    'text',
    'user',
  ];

  /**
   * The builder under test.
   */
  private EditorialDataBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('media');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter']);

    NodeType::create([
      'type' => 'recipe',
      'name' => 'Recipe',
    ])->save();
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
    NodeType::create([
      'type' => 'collection',
      'name' => 'Collection',
    ])->save();

    $this->createUserField('field_role', 'string');
    $this->createUserField('field_bio', 'string_long');
    $this->createNodeField('recipe', 'field_description', 'string_long');
    $this->createNodeField('article', 'field_description', 'string_long');
    $this->createNodeField('collection', 'field_description', 'string_long');
    $this->createNodeField('article', 'field_content', 'text_long');
    $this->createNodeField('collection', 'field_content', 'text_long');

    $this->builder = new EditorialDataBuilder(
      $this->container->get('entity_type.manager'),
      $this->container->get('file_url_generator'),
      $this->container->get('date.formatter'),
      $this->container->get('menu.link_tree'),
    );
  }

  /**
   * Verifies masthead cards come from editorial profile data, not email domain.
   */
  public function testBuildAuthorCardsUsesEditorialFields(): void {
    User::create([
      'name' => 'Admin',
      'mail' => 'admin@example.com',
      'status' => 1,
    ])->save();

    $author = User::create([
      'name' => 'Ana Silva',
      'mail' => 'ana@umami.test',
      'status' => 1,
      'field_role' => 'Editor',
      'field_bio' => 'Writes about dinner.',
    ]);
    $author->save();

    User::create([
      'name' => 'Pat Reader',
      'mail' => 'pat@example.com',
      'status' => 1,
    ])->save();

    $cards = $this->builder->buildAuthorCards();

    $this->assertCount(1, $cards);
    $this->assertSame('Ana Silva', $cards[0]['name']);
    $this->assertSame('Editor', $cards[0]['role']);
  }

  /**
   * Verifies search uses stored fields and bundle filters with real totals.
   */
  public function testSearchResultsCanBeCountedAndFilteredByBundle(): void {
    Node::create([
      'type' => 'recipe',
      'title' => 'Spring Market Pasta',
      'status' => 1,
      'field_description' => 'Pasta for the Saturday market haul.',
    ])->save();

    Node::create([
      'type' => 'article',
      'title' => 'Market Notes',
      'status' => 1,
      'field_description' => 'A short deck.',
      'field_content' => [
        'value' => 'This story starts at the market and ends at dinner.',
        'format' => 'plain_text',
      ],
    ])->save();

    Node::create([
      'type' => 'collection',
      'title' => 'Spring suppers',
      'status' => 1,
      'field_description' => 'A deck without the keyword.',
      'field_content' => [
        'value' => 'A collection of market cooking ideas.',
        'format' => 'plain_text',
      ],
    ])->save();

    Node::create([
      'type' => 'recipe',
      'title' => 'Braised fennel',
      'status' => 1,
      'field_description' => 'No keyword here.',
    ])->save();

    $this->assertSame(3, $this->builder->countSearchResults('market', ''));
    $this->assertSame(1, $this->builder->countSearchResults('market', 'article'));

    $results = $this->builder->buildSearchResults('market', 'article', 10);

    $this->assertCount(1, $results);
    $this->assertSame('article', $results[0]['type']);
    $this->assertSame('Market Notes', $results[0]['title']);
  }

  /**
   * Verifies simple template links come from editable Drupal menus.
   */
  public function testBuildMenuLinksUsesMenuContent(): void {
    Menu::create([
      'id' => 'social',
      'label' => 'Social',
    ])->save();

    MenuLinkContent::create([
      'title' => 'Substack',
      'link' => ['uri' => 'https://substack.com'],
      'menu_name' => 'social',
      'weight' => 1,
    ])->save();
    MenuLinkContent::create([
      'title' => 'Instagram',
      'link' => ['uri' => 'https://instagram.com'],
      'menu_name' => 'social',
      'weight' => 0,
    ])->save();
    MenuLinkContent::create([
      'title' => 'RSS',
      'link' => ['uri' => 'internal:/feed'],
      'menu_name' => 'social',
      'weight' => 2,
    ])->save();

    $links = $this->builder->buildMenuLinks('social');

    $this->assertSame(['Instagram', 'Substack', 'RSS'], array_column($links, 'title'));
    $this->assertSame('/feed', $links[2]['url']);
  }

  /**
   * Creates a user field for the test site.
   */
  private function createUserField(string $field_name, string $field_type): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'user',
      'type' => $field_type,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => $field_name,
    ])->save();
  }

  /**
   * Creates a node field for the test bundles.
   */
  private function createNodeField(string $bundle, string $field_name, string $field_type): void {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => 1,
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $field_name,
    ])->save();
  }

}
