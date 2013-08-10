`flow` is tiny piece of code, that uses miracle of PHP 5.5 Generators and yield
keyword and allows you to write asynchronous code that looks just like
imperative one, but it uses under the hood React.PHP promises.


```php
// this flow code and next snippet does tha same thing
$p = flow(function () {
  $a = yield asyncTask(1);
  $b = yield asyncTask($a + 10);
       yield asyncTask($b + 100)
});

$p = asyncTask(1)->then(function ($a) {
  return asyncTask($a + 10)->then(function ($b) {
    return asyncTask($b + 100);
  });
});
```

`flow` function always return Promise



If yielded promise fails, it deosn't throw exception into generator code, but
rest of the code is not executed (and error is propagated).

```php
flow(function () {
  $a = yield asyncTask(1);
  $b = yield asyncTask(2); // if this promise fails, next lines are not executed and flow returns this failed promise
  $b = yield asyncTask(3);
  yield [$a, $b, $c];
});
```

You can yield not only promises. It's useful for returning final value, as
shown in previous example.




You can use loops (code looks just like imperative one, but it's asynchronous
and uses promises under the hood).

```php
flow(function() {
  $profile = (yield fetchProfile('@kaja47'));

  $ids = [];
  $cursor = '-1';
  do {
    $fs = (yield fetchFollowers($profile->id, $cursor));
    $ids = array_merge($ids, $fs->ids);
    $cursor = $fs->next_cursor_str;
  } while ($cursor != "0")

  yield [$profile, $ids];
});
```


And exceptions

```php
flow(function () use ($id) {
  if ($id < 0)
    throw new Exception("id should be equal or greater that zero");

  $profile = yield fetchProfile($id);
  $lastArticles = yield fetchLastArticles($id, $profile->showLastArticles);
  yield [$profile, $lastArticles];
});
```

This produce rejected promise if $id is lower than zero, with exception as value of rejected promise.
