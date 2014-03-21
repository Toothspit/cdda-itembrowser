<?php
namespace Repositories;

use Illuminate\Cache\CacheManager;

class JsonCache extends Json implements RepositoryInterface
{
  const CACHE_KEY="json";
  private $dataChunks;
  private $indexChunks;

  private $cache;

  public function __construct(CacheManager $cache)
  {
    $this->dataChunks = array();
    $this->indexChunks = array();
    $this->indexHashSize = null;
    $this->cache = $cache;
    parent::__construct();
  }

  protected function read()
  {
    $key = self::CACHE_KEY;

    $lock_fp = fopen(storage_path()."/cache/.json.lock", "w+");
    flock($lock_fp, LOCK_EX);

    if($this->cache->has("$key:loaded")) {
      $this->indexHashSize = $this->cache->get("$key:indexHashSize");
      flock($lock_fp, LOCK_UN);
      fclose($lock_fp);

      return;
    }

    // clear all cache, this ensures searches are read again.
    $this->cache->flush();

    parent::read();

    $database = $this->chopDatabase($this->database);
    foreach($database as $chunk=>$data) {
      $this->cache->put("$key:db:$chunk", $data, 60);
    }

    $this->indexHashSize = $this->makeIndexHashSize();

    $index = $this->chopIndex($this->index);
    foreach($index as $chunk=>$data)
    {
      $this->cache->put("$key:index:$chunk", $data, 60);
    }

    $this->cache->put("$key:indexHashSize", $this->indexHashSize, 60);

    $this->cache->put("$key:loaded", true, 60);

    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
  }

  private function makeDatabaseHash($repo_id)
  {
    return intval($repo_id/100);
  }

  private function chopDatabase($database)
  {
    $chunks = array();
    $newDatabase = array();
    foreach($database as $repo_id=>$object) {
      $chunk = $this->makeDatabaseHash($repo_id);
      $chunks[$repo_id] = $chunk;
      $newDatabase[$chunk][$repo_id] = $object;
    }
    return $newDatabase;
  }

  protected function loadObject($repo_id)
  {
    if(isset($this->database[$repo_id]))
      return;

    $chunk = $this->makeDatabaseHash($repo_id);

    if(!isset($this->dataChunks[$chunk])) {
      $key = self::CACHE_KEY;
      $this->dataChunks[$chunk] = $this->cache->get("$key:db:$chunk");
    }
    $this->database[$repo_id] = $this->dataChunks[$chunk][$repo_id];
  }


  private function makeIndexHashSize()
  {
    return 10;
  }

  private function makeIndexHash($index)
  {
    $size = $this->indexHashSize;

    // the item index is quite big by itself, use a chunk just for it.
    if ($index=="item") 
      return 0;

    // the recipe index is common enough to be in a chunk by itself.
    if ($index=="recipe")
      return 1;

    return abs(intval(base_convert($index, 16, 10)/$size)%$size)+2;
  }

  private function chopIndex($database)
  {
    $chunks = array();
    $newDatabase = array();
    foreach($database as $index=>$data) {
      $chunk = $this->makeIndexHash($index);
      $chunks[$index] = $chunk;
      $newDatabase[$chunk][$index] = $data;
    }
    return $newDatabase;
  }

  protected function loadIndex($index)
  {
    if(isset($this->index[$index])) 
      return;

    $chunk = $this->makeIndexHash($index);
    if(!isset($this->indexChunks[$chunk])) {
      $key = self::CACHE_KEY;
      $this->indexChunks[$chunk] = $this->cache->get("$key:index:$chunk");
    }

    if(!isset($this->indexChunks[$chunk][$index]))
      return;

    $this->index[$index] = $this->indexChunks[$chunk][$index];
  }
}
