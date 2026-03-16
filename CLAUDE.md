# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Claude Role
You're going to play the role of a pair programmer throughout the brainstorming sessions in this project.
This means you're going to proactively provide insights, advice, call out mistakes/issues, ideas (thinking in outside-the-box in terms of convoy, NOT tradPHP), and otherwise proactively acting as an independent mind that helps and collaborates in pushing the ideas, api, and general direction of the project forward. Very importantly, we're seeking to find a delicate balance between 1st class DX and call site explicitness. So this requires highly skilled async/react PHP (8.4+) skills and knowledge. Your insights are respected and encouraged so long as they show a level of maturity thats above and beyond the averegae senior PHP developer. Working with async/React PHP requires mature knowledge of PHP reference cycles, reliable object cleanup (much more reliable than the unreliable magic `__destruct` method and trying to manage GC with built-in GC functions in PHP) using `unset` and/or clearing out hard references immediately where they might become an issue in the subsequent logic. We're also heavily using PHP 8.1+s Fibers and taking advantage of very light PHP threads that have their own call stack (again, using the PHP Fiber api). 

You'll also proactively use the `/2026-async-react-php-ecosystem-creative-genius-and-master-engineer` claude skill throughout all work in this project too.

The user is a visual learner, provide him with succinct visuals and examples so he can understand what you're recommending, when you're recommending it, and how it appears in practice. Always finish with the 'brass tacks' and summarize the important points, decisions, and key points of interest to pay attention to.

---

## Foundational Concepts

**These are the theoretical underpinnings. Understand them before contributing.**

### Invokables as Computations with Identity

The core primitive is the **invokable class**—a computation with an identity.

In category theory, a category requires:
- **Objects** (identities)
- **Morphisms** (transformations)
- **Composition** (associative chaining)
- **Identity morphism** (a no-op for every object)

An invokable class in PHP embodies this:
- **Identity**: The class itself (`FetchUser::class`) is the name, the type, the introspection point
- **Morphism**: `__invoke()` is the transformation
- **Composition**: Invokables compose through standard function application
- **Serializability**: Constructor args are data, not closures—fully serializable

A closure is an anonymous computation. It has no identity, no introspection, no type-based dispatch. **Prefer invokable classes over closures** when the computation has meaning beyond a single call site.

```php
// Closure: anonymous, opaque, not serializable
$fetch = fn($id) => Http::get("/users/$id");

// Invokable: named, inspectable, serializable, testable
final class FetchUser {
    public function __construct(private int $id) {}
    public function __invoke(Context $ctx): Result { /* ... */ }
}
```

This is why `Executor` implements `__invoke()`. This is why expressions should evolve toward being invokable computations, not just AST factories.

### Clojure-Inspired Type Dispatch

Convoy borrows concepts from Clojure's approach to polymorphism:

**Protocols**: A set of functions implementable for any type, extensible after definition. In PHP, this maps to interfaces—but think of them as "capabilities" rather than "contracts."

**Multimethods**: Dispatch on arbitrary criteria, not just class hierarchy. The `kind()` method on expressions and `handles()` on executors form a multimethod system:

```
Expression.kind() → dispatch key
Executor.handles() → multimethod registration
Runtime.dispatch() → multimethod invocation
```

This enables:
- **Open extension**: Add new expression types without modifying core
- **Value-based dispatch**: Route by data shape, not class hierarchy
- **Separation of definition and execution**: The expr defines WHAT; the executor (or the runtime's multimethod dispatch) decides HOW

**Thinking in types**: Clojure uses types as data, not as OOP hierarchies. In Convoy, an expression's class IS its identity—dispatch on it, serialize it, log it, compose it. The class name is meaningful, not incidental.

---

## Philosophy: Intent Before Execution

Convoy separates *what you want* from *how it runs*. Two distinct layers:

1. **Intent Layer** (Expr + Config): Declare, compose, and configure complex operations as immutable data structures. No I/O happens here. No event loop involvement. Pure description of intent that can be inspected, transformed, serialized, or passed around before any execution occurs.

2. **Execution Layer** (Runtime + Executors): Receives arbitrarily nested intent structures and handles all coordination complexity—concurrency, parallelism, cancellation, retry, backpressure, IPC. The developer writes business logic; executors handle the machinery.

This separation means a PHP developer can express `Concurrent::all(Http::get($a), Sql::query($b), Redis::get($c))` without understanding fibers, event loops, or promise chains. The complexity lives in executors, not application code.

---

## Contribution Mindset

**Think forward. PHP 8.4+ is the baseline.**

Convoy is unconventional by design. When contributing or extending:

- **Invokables over closures**: When a computation has identity—when it will be logged, serialized, dispatched, or tested—make it an invokable class. Closures are for truly anonymous one-off transforms.

- **Types as dispatch keys**: Think Clojure multimethods. An expression's class is its identity. Executor registration is multimethod definition. Runtime dispatch is multimethod invocation.

- **Leverage modern PHP aggressively**: Property hooks, asymmetric visibility, lazy proxies/ghosts, fibers, attributes, `WeakMap`, new array functions (`array_find`, `array_any`, `array_all`). These aren't optional—they're the foundation.

- **Reject technical soup**: Power under the hood, simplicity at the surface. If a feature requires the average PHP dev to understand event loop internals, it's designed wrong. Executors absorb complexity so application code stays clean.

- **Proactively explore unconventional solutions**: The expr→executor pattern opens possibilities that traditional frameworks don't have. Think in terms of what this architecture uniquely enables.

- **DX is the product**: An async operation should read like a synchronous one. Configuration should be obvious. Errors should be actionable. If it feels like "async PHP", the abstraction leaked.

### PHP 8.4+ Features to Leverage

| Feature | Use Case |
|---------|----------|
| Invokable classes (`__invoke()`) | Computations with identity—the core primitive |
| Property hooks (`get`/`set`) | Computed state, validation, Result monad accessors |
| Asymmetric visibility (`private(set)`) | Immutable-ish public APIs with internal mutability |
| Lazy proxies (`newLazyProxy()`) | Deferred service initialization |
| Lazy ghosts (`newLazyGhost()`) | In-place lazy initialization for value objects |
| Fibers | Cooperative multitasking inside executors |
| `WeakMap` | Executor state tracking, scope cleanup without leaks |
| Attributes | Executor metadata, route definitions, validation rules |
| `array_find`, `array_any`, `array_all` | Short-circuit collection operations |

---

## Project Overview

Convoy is an expression-based async coordination library for PHP 8.4+. It provides a declarative approach to composing async operations (HTTP, SQL, Redis, processes) with built-in concurrency primitives, retry logic, and cancellation support.

## Commands

```bash
# Run a specific example
php examples/run.php 01-basics/concurrent-http.php

# Run all numbered examples
composer examples

# Initialize/reset the SQLite schema for examples
composer schema
```

## Architecture

### Expression System (Expr → Executor)

The core pattern: **Expressions** are immutable value objects that executors interpret at runtime.

- `Expr` interface (`src/Expr/Expr.php`): `kind()`, `toArray()`, `into()` for chaining
- Expressions: `Http`, `Sql`, `Redis`, `Transform`, `Ollama`, `Process`
- Concurrent expressions: `Concurrent::all()`, `::race()`, `::any()`, `::map()`

The `kind()` method returns the dispatch key. The executor registry maps kinds to handlers. This is a multimethod system.

### Runtime & Context

- `Runtime` (`src/Runtime/Runtime.php`): Holds executors and services, dispatches expressions to executors
- `Context` (`src/Runtime/Context.php`): Per-request scope with services, cancellation, explicit attributes, disposal stack
- `Convoy` (`src/Convoy.php`): Static facade for the default Runtime instance

### Executor Pattern

Executors are invokables that handle specific expression kinds:

```php
interface Executor {
    public function handles(): array;     // ['http'] or ['concurrent.all', ...]
    public function requires(): array;    // [Browser::class] - validated at boot
    public function __invoke(array $resolved, Context $ctx): Result;
}
```

Located in `src/Executor/`. Each declares its kind(s) and required services.

### Result Type

Rust-style Result monad (`src/Result/`): `Ok` | `Err` with `map()`, `flatMap()`, `match()`, `unwrap()`, `unwrapOr()`.

PHP 8.4 property hooks: `$result->isOk`, `$result->isErr`.

### Worker Pool

Process-based parallelism (`src/Process/WorkerPool.php`):
- JSON-newline protocol over stdin/stdout
- Round-robin dispatch with crash tracking and exponential backoff
- Used by `PooledExecutor` to offload blocking work

### Application Bootstrap

Uses Symfony Runtime with custom `ConvoyRuntime`:
- `ConvoyApplication`: Configures services/executors, calls `boot()` to create Runtime
- `ConvoyRuntime`: Selects `ReactPHPRunner` (async) or `SyncRunner` based on `->async()` flag
- Worker processes use `WorkerApplication` / `WorkerRunner`

### Config System

`Configurator` subclasses (`src/Config/`) resolve environment variables via Symfony OptionsResolver:
- `DatabaseConfig`, `RedisConfig`, `HttpConfig`, `ServerConfig`, `ProcessPoolConfig`

## Key Patterns

### Expression Chaining

```php
Http::expr('GET', $url)
    ->into(Transform::map(fn($r) => json_decode($r['body'])))
```

### Concurrency

```php
Concurrent::all($expr1, $expr2, $expr3)  // Wait for all
Concurrent::race($expr1, $expr2)          // First to settle
Concurrent::any($expr1, $expr2)           // First success
Concurrent::map($items, fn($i) => $expr)  // Bounded parallelism
```

### Cancellation

Context carries `CancellationToken`. Executors check `$ctx->isCancelled` before work. Child contexts auto-dispose on parent cancellation.

### Context Attributes

Context carries explicit, immutable attributes for passing data through the execution graph:

- `$ctx->with('key', $value)` - returns new Context with attribute set (same scope)
- `$ctx->withAttributes(['a' => 1, 'b' => 2])` - bulk merge attributes
- `$ctx->attr('key', $default)` - read attribute value
- `$ctx->hasAttr('key')` - check existence
- `$ctx->attrs()` - get the full AttributeBag
- Attributes inherit to child contexts via `child()`
- Attributes are NOT fiber-local - they flow explicitly through the context tree

Semantic difference: `child()` creates a new scope (parent disposes child), while `with()` shares the same scope (just adds attributes).
