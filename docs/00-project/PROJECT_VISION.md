# Project Vision

> **Status:** Approved
> **Last updated:** 2026-08-06

## Purpose

Define what ERP Hajj is, who it serves, and the qualities the platform must uphold. This document is a source of truth for project direction.

## Vision

ERP Hajj is a **multi-tenant Arabic enterprise web platform** for managing and governing **Hajj campaign companies** (شركات وحملات الحج). Each tenant is an independent Hajj campaign company or organization; tenant data remains strictly isolated.

The system connects executive management, department managers, supervisors, and employees around one governed operational core: users, roles and permissions, organizational structure, contracts, meetings, decisions, tasks, documents, warehouses, inventory, custodies (عُهد), assets, notifications, administrative indicators, and audit logs.

## Core Qualities

- **Scalable** — SaaS-ready, multi-tenant from day one.
- **Secure** — strict tenant isolation; cross-tenant access must be impossible.
- **Auditable** — every critical operation leaves an immutable audit record.
- **Maintainable** — modular monolith, clear layering, two developers collaborating via GitHub pull requests.

## Delivery Decisions

- Current delivery is **web only** (responsive, desktop-first).
- The REST API (`/api/v1`) must be reusable by Android and iOS applications in future phases. Mobile apps are **not** implemented now.
- **Arabic** is the primary language; **RTL** is the primary layout; the sidebar is on the **right**.

## Current Decisions

- Stack: Laravel 12 (PHP 8.2+) + Sanctum + MySQL backend; Vue 3 + TypeScript frontend. See [ARCHITECTURE.md](../02-architecture/ARCHITECTURE.md).
- Monorepo ([ADR-0001](../10-decisions/ADR-0001-MONOREPO.md)); modular monolith ([ADR-0002](../10-decisions/ADR-0002-MODULAR-MONOLITH.md)).
- Multi-tenancy: single application, single database, shared schema with `tenant_id` ([ADR-0003](../10-decisions/ADR-0003-MULTI-TENANCY.md)).
- Terminology: **Tenant** is the technical term; **Organization / Campaign Company** (منظمة / شركة حملة) is the business-facing term.

## TBD

- Commercial model and subscription/payment ownership: TBD.
- Infrastructure ownership: TBD (see [ENVIRONMENTS.md](../08-deployment/ENVIRONMENTS.md)).
