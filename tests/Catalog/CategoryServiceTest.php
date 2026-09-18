<?php

namespace Tests\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryService;
use RuntimeException;
use Tests\TestCase;

/**
 * CategoryService আসল DB টাচ করে (Model/Service লেয়ারে mock নাই — Decision D-05)।
 * Tests\TestCase প্রতিটা টেস্ট মেথডকে একটা ট্রানজেকশনে মুড়ে শেষে rollback করে,
 * তাই এখানে যা কিছু save/delete হচ্ছে তার কিছুই আসল ক্যাটাগরি টেবিলে থেকে যাবে না।
 */
final class CategoryServiceTest extends TestCase
{
    public function test_save_creates_top_level_category_with_root_path(): void
    {
        $id = CategoryService::save(['name' => 'Zz Test Parent', 'parent_id' => 0]);

        $row = Category::find($id);

        self::assertSame('/' . $id . '/', $row['path']);
        self::assertSame(0, (int) $row['depth']);
        self::assertSame('zz-test-parent', $row['slug']);
    }

    public function test_save_rebuilds_path_and_depth_for_children(): void
    {
        $parentId = CategoryService::save(['name' => 'Zz Test Parent', 'parent_id' => 0]);
        $childId  = CategoryService::save(['name' => 'Zz Test Child', 'parent_id' => $parentId]);

        $child = Category::find($childId);

        self::assertSame("/$parentId/$childId/", $child['path']);
        self::assertSame(1, (int) $child['depth']);
    }

    public function test_moving_a_parent_rebuilds_every_descendant_path(): void
    {
        $rootId   = CategoryService::save(['name' => 'Zz Test Root', 'parent_id' => 0]);
        $branchId = CategoryService::save(['name' => 'Zz Test Branch', 'parent_id' => 0]);
        $leafId   = CategoryService::save(['name' => 'Zz Test Leaf', 'parent_id' => $branchId]);

        // branch-কে root এর নিচে সরানো হলো — leaf এর path/depth নিজে থেকেই বদলে যাওয়া উচিত
        CategoryService::save(['name' => 'Zz Test Branch', 'parent_id' => $rootId], $branchId);

        $leaf = Category::find($leafId);

        self::assertSame("/$rootId/$branchId/$leafId/", $leaf['path']);
        self::assertSame(2, (int) $leaf['depth']);
    }

    public function test_a_category_cannot_become_its_own_parent(): void
    {
        $id = CategoryService::save(['name' => 'Zz Test Self', 'parent_id' => 0]);

        $this->expectException(RuntimeException::class);

        CategoryService::save(['name' => 'Zz Test Self', 'parent_id' => $id], $id);
    }

    public function test_a_sub_category_cannot_be_made_its_own_ancestors_parent(): void
    {
        $parentId = CategoryService::save(['name' => 'Zz Test Cycle Parent', 'parent_id' => 0]);
        $childId  = CategoryService::save(['name' => 'Zz Test Cycle Child', 'parent_id' => $parentId]);

        $this->expectException(RuntimeException::class);

        // parent-কে নিজের child এর নিচে সরানোর চেষ্টা — চক্র তৈরি হয়ে যেত
        CategoryService::save(['name' => 'Zz Test Cycle Parent', 'parent_id' => $childId], $parentId);
    }

    public function test_delete_is_blocked_while_sub_categories_exist(): void
    {
        $parentId = CategoryService::save(['name' => 'Zz Test Parent 2', 'parent_id' => 0]);
        CategoryService::save(['name' => 'Zz Test Child 2', 'parent_id' => $parentId]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sub-categories');

        CategoryService::delete($parentId);
    }

    public function test_delete_succeeds_once_childless(): void
    {
        $id = CategoryService::save(['name' => 'Zz Test Leaf Only', 'parent_id' => 0]);

        self::assertTrue(CategoryService::delete($id));
        self::assertSame([], Category::find($id));
    }
}
