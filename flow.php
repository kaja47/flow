<?php

use React\Promise\PromiseInterface;
use React\Promise\When;

/**
 * @param Closure|Generator
 */
function flow($f) {
  if ($f instanceof \Generator) {
    $gen = $f;
  } elseif ($f instanceof \Closure) {
    $gen = $f();
  } else {
    throw new \InvalidArgumentException('Function flow expects Generator of Closure as argument');
  }

  $first = true;
  $recur = function($pureValue) use($gen, &$recur, &$first) {
    try {
      $x = $first ? $gen->current() : $gen->send($pureValue);
    } catch (\Exception $e) {
      return When::reject($e);
    }
    $first = false;
    if (!$gen->valid())                 return $pureValue;
    if ($x instanceof PromiseInterface) return $x->then($recur);
    else                                return $recur($x);
  };

  return When::resolve($recur(null));
}
