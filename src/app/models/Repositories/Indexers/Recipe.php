<?php
namespace Repositories\Indexers;

class Recipe implements IndexerInterface
{
    const DEFAULT_INDEX = "recipe";
    const ID_FIELD = "repo_id";

    private function itemQualityLevel($item, $quality)
    {
        foreach ($item->qualities as $q) {
            if ($q[0] == $quality) {
                return $q[1];
            }
        }
    }

    public function onFinishedLoading($repo)
    {
        foreach ($repo->all(self::DEFAULT_INDEX) as $id) {
            $recipe = $repo->get(self::DEFAULT_INDEX, $id);
            // search for all the items with the apropiate qualities
            if (isset($recipe->qualities)) {
                foreach ($recipe->qualities as $group) {
                    foreach ($repo->all("quality.$group->id") as $id => $item) {
                        $item = $repo->get("item", $id);
                        if ($this->itemQualityLevel($item, $group->id)<$group->level) {
                            continue;
                        }
                        $this->linkIndexes($repo, 'toolFor', $id, $recipe);
                    }
                }
            }

            if (isset($recipe->skill_used)) {
                $skill = $recipe->skill_used;
                $level = $recipe->difficulty;

                $item = $repo->get("item", $recipe->result);
                $repo->addIndex("skill.$skill.$level", $item->id, $item->repo_id);
                $repo->addIndex("skills", $skill, $skill);
            }
        }
    }

    private function linkIndexes($repo, $key, $id, $recipe)
    {
        // NONCRAFT recipes go directly to the disassembly index,
        // they are not needed anywhere else.
        if ($key == "recipes"
            and $recipe->category == "CC_NONCRAFT") {
            $repo->addIndex("item.disassembly.$id", $recipe->repo_id, $recipe->repo_id);

            return;
        }

        // reversible recipes go to the disassembly index,
        // but they're used to craft, so process further indexes.
        if ($key == "recipes"
        and isset($recipe->reversible)
        and $recipe->reversible == "true") {
            $repo->addIndex("item.disassembly.$id", $recipe->repo_id, $recipe->repo_id);
        }

        if ($key == "toolFor") {
            // create a list of recipe categories, excluding NONCRAFT.
            if ($recipe->category != "CC_NONCRAFT") {
                $repo->addIndex("item.categories.$id", $recipe->category, $recipe->category);
            }

            // create a list of tools per category for this object.
            $repo->addIndex("item.toolForCategory.$id.$recipe->category",
                $recipe->repo_id, $recipe->repo_id);
        }

        $repo->addIndex("item.$key.$id", $recipe->repo_id, $recipe->repo_id);
    }

    public function onNewObject($repo, $object)
    {
        if ($object->type == "recipe") {
            $recipe = $object;

            $repo->addIndex(self::DEFAULT_INDEX, $recipe->repo_id, $recipe->repo_id);

            if (isset($recipe->result)) {
                $this->linkIndexes($repo, "recipes", $recipe->result, $recipe);
                if (isset($recipe->book_learn)) {
                    foreach ($recipe->book_learn as $learn) {
                        $this->linkIndexes($repo, "learn", $learn[0], $recipe);
                    }
                }
            }

            if (isset($recipe->tools)) {
                foreach ($recipe->tools as $group) {
                    foreach ($group as $tool) {
                        list($id, $amount) = $tool;
                        $this->linkIndexes($repo, "toolFor", $id, $recipe);
                    }
                }
            }

            if (isset($recipe->components)) {
                foreach ($recipe->components as $group) {
                    foreach ($group as $component) {
                        list($id, $amount) = $component;
                        $this->linkIndexes($repo, "toolFor", $id, $recipe);

                        if ($recipe->category == "CC_NONCRAFT"
              or (isset($recipe->reversible)
              and $recipe->reversible == "true")) {
                            $repo->addIndex("item.disassembledFrom.$id", $recipe->repo_id, $recipe->repo_id);
                        }
                    }
                }
            }
        }
    }
}
