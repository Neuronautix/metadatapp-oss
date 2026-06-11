# CONNECTED_APPS.md

## Purpose

Connected Apps are external systems integrated with Metadatapp (MAPP).

They are not treated as special cases but as **plugins** following a common contract,
allowing new integrations to be added with minimal effort.

The integration layer is built around generic Symfony / API Platform mechanisms:
State Providers, State Processors, interfaces, and declared sync capabilities.

---

## Generic Integration Model

Each Connected App declares:

* Which entities it can sync
* In which direction (fetch, push, or both)
* Which interfaces it implements
* Which mapping strategy it uses

No Connected App is hard-coded into the core.

The core only depends on **contracts**.

---

## Generic State Provider

A generic API Platform State Provider is used to fetch data from Connected Apps.

It allows:

* Fetching from an external API instead of the database
* Switching data sources transparently
* Combining external data with local persistence when needed

### Fetch Strategies

Each entity can define its fetch strategy:

1. External App as Source of Truth  
   Data is always fetched from the external API.

2. Fetch with Persistence  
   External data is fetched and stored locally for reuse.

3. Fetch without Persistence  
   External data is fetched only for display or comparison.

4. Repository-Based Fetch  
   Data is fetched from Doctrine when local storage is authoritative.

5. Hybrid Provider  
   Provider decides dynamically based on context.

This allows full flexibility without changing API endpoints.

---

## Generic State Processor

A generic State Processor handles push operations.

It allows:

* Sending data to external apps
* Triggering sync on write operations
* Orchestrating export logic

Push logic is never embedded in controllers.

---

## Entity Sync Declaration

Each entity declares:

* Which Connected Apps it supports
* Whether it supports fetch, push, or both
* Which fields are mapped
* Which identifiers are used

Example conceptually:

```

Experiment supports private integration (fetch + push)
Experiment supports Fair3r (fetch only)

```

This makes integration declarative instead of procedural.

---

## Fetch Triggers

Fetch operations can be triggered by:

* UI button
* API endpoint
* Scheduled job
* CLI command
* Internal event

The same fetch logic is reused regardless of the trigger.

---

## Push Triggers

Push operations can be triggered by:

* State Processor on entity write
* Mercure event
* UI action
* API call
* Scheduler
* CLI command
* When another Connected App finishes syncing

Push is therefore:

* Event-driven
* User-driven
* Or system-driven

---

## Sync Command Layer

Each Connected App exposes generic sync commands:

* Fetch command
* Push command
* Combined sync command

Commands are orchestration only.
They never contain business logic.

---

## UI Integration

The UI can:

* Trigger fetch
* Trigger push
* Display sync status
* Display last sync date
* Display error state

UI never talks directly to external apps.
It only triggers Metadatapp (MAPP) sync flows.

---

## External Identifiers

External IDs are stored as:

```

{appName}Id

```

Example:

* externalSystemId
* fair3rId

They will later evolve into a many-to-many association table.

---

## Mocking Strategy

Each Connected App provides a Mock HttpClient.

Mocks must:

* Respect API contracts
* Be deterministic
* Be fast
* Be replaceable transparently

---

## Error Strategy

Failures must:

* Never block the API
* Be logged
* Be retryable
* Preserve progress

Sync is always resumable.

---

## Contract Stability

Breaking changes require:

* Versioning
* Migration plan
* Documentation update

---

## Design Principle

Connected Apps are:

Plugins, not dependencies.

Metadatapp (MAPP) must work with zero Connected Apps enabled.

---

### Architecture Diagram

graph TD

UI[UI / Frontend]
API[API Platform]
Provider[Generic State Provider]
Processor[Generic State Processor]

API --> Provider
API --> Processor

Provider -->|fetch| ExternalApp1[private integration API]
Provider -->|fetch| ExternalApp2[Fair3r API]

Processor -->|push| ExternalApp1
Processor -->|push| ExternalApp2

API --> Repo[Doctrine Repository]
Provider --> Repo
Processor --> Repo

---

## Summary

The Connected Apps system is:

* Declarative
* Extensible
* Event-driven
* Testable
* UI-agnostic
* Scheduler-agnostic
* API-agnostic

Adding a new Connected App means:

Implement interfaces → declare entities → configure mapping → done.
