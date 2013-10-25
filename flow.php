<?php

namespace Atrox;

use React\Promise\PromiseInterface;
use React\Promise\When;
use React\Promise\Deferred;


class Async {

  /**
   * @param Closure|Generator
   */
  static function getGenerator($f) {
    if ($f instanceof \Generator) {
      return $f;
    } elseif ($f instanceof \Closure) {
      return $f();
    } else {
      throw new \InvalidArgumentException('Generator of Closure expected');
    }
  }

  static function flow($f) {
    $gen = self::getGenerator($f);

    $throwExc = function ($ex) use ($gen) { $gen->throw($ex); };

    $first = true;
    $recur = function($pureValue) use($gen, &$recur, &$first, $throwExc) {
      try {
        $x = $first ? $gen->current() : $gen->send($pureValue);
      } catch (\Exception $e) {
        return When::reject($e);
      }
      $first = false;
      if (!$gen->valid())                 return $pureValue;
      if ($x instanceof PromiseInterface) return $x->then($recur, $throwExc);
      else                                return $recur($x);
    };

    return When::resolve($recur(null));
  }


}

class Chain {

  /**
   * G[P] => P[(V, P[Next])]
   * @param Closure|Generator
   */
  static function promiseChainSeq($f) {
    $gen = Async::getGenerator($f); // generator of promises

    $throwExc = function ($ex) use ($gen) { $gen->throw($ex); };

    $first = true;
    $recur = function($pureValue) use($gen, &$recur, &$first, $throwExc) {
      try {
        $x = $first ? $gen->current() : $gen->send($pureValue);
      } catch (\Exception $e) {
        return When::reject($e);
      }
      $first = false;
      if (!$gen->valid())
        return When::resolve([$pureValue, null]);

      return When::resolve($x)->then(
        function ($val) use ($recur) { return [$val, $recur($val)]; },
        function ($err) use ($gen)   { $gen->throw($err); }
      );
    };

    return $recur(null);
  }

  static function promiseChainPar($f) {
    return self::concurrently(PHP_INT_MAX, $f);
  }

  static function flattenChains($ch) {
    $firstDeferred = $deferred = new Deferred;

    $makeChain = function ($v) use (&$deferred) {
      $newDeferred = new Deferred;
      $deferred->resolve([$v, $newDeferred->promise()]);
      $deferred = $newDeferred;
    };

    $unwrapSub = function (array $pair) use ($makeChain, &$unwrapSub) {
      list($v, $next) = $pair;
      $makeChain($v);
      if ($next !== null) {
        $next->then($unwrapSub);
      }
    };

    $unwrap = function (array $pair) use (&$unwrap, $unwrapSub, $makeChain) {
      list($subChain, $next) = $pair;
      // ??? empty subchain
      $subChain = When::resolve($subChain); // needed because `then` acts as both map and flatMap and automatically flattens nested promises
      $subChain->then($unwrapSub);
      if ($next !== null)
        $next->then($unwrap);
    };

    $ch->then($unwrap);

    return $firstDeferred->promise();
  }

  static function concurrently($n, $f) {
    $gen = Async::getGenerator($f);

    $firstDeferred = $deferred = new Deferred;

    $makeChain = function ($v) use (&$deferred) {
      $newDeferred = new Deferred;
      $deferred->resolve([$v, $newDeferred->promise()]);
      $deferred = $newDeferred;
    };

    $runNext = function () use ($gen, &$runNext, $makeChain) {
      if ($gen->valid()) {
        $p = $gen->current();
        $gen->next();
        $p->then($makeChain); // throw away errors?
        $p->then($runNext, $runNext);
      }
    };

    for ($i = 0; $i < $n; $i++) {
      $runNext();
    }

    return $firstDeferred->promise();
  }
}
