# Overview — Curricular Repository System

## What this is

A web application that manages the curricular structure of academic programs: study plans, the courses within them, and — most importantly — how courses from an old study plan map onto courses in a new one when a program updates its curriculum.

It is not a generic CRUD app for storing academic records. Its core responsibility is maintaining a **directed graph of course equivalencies between study plans** and guaranteeing that graph is always internally consistent: no contradictions, no circular reasoning about what equals what.

## The problem it solves

Academic programs periodically update their study plans — new courses replace old ones, requirements change, curricula get modernized. When that happens, someone has to decide: *if a student already passed Course A under the old plan, does that count as having passed Course B under the new plan?* That decision is called an **equivalency**, and it's backed by an official resolution document.

Today, that knowledge lives in the head of whoever manages the program — a Program Director, a teaching coordinator. When that person retires, changes roles, or is simply unavailable, the knowledge doesn't transfer. It's scattered across personal folders, emails, and memory. There's no single place the institution can check to answer "is this equivalency valid?" or "which resolution governs this course's teaching modality?"

This system replaces that with a centralized, versioned, and auditable source of truth. Nothing here is a formality — an equivalency is directly consequential: it determines whether a real student has to retake a course they already passed, or gets incorrectly waived from something they never actually took. A design error here costs a student a semester.

## What "hard" actually means for this project

Storing plans, courses, and equivalency records is the easy part. The hard part is the integrity guarantees around the equivalency graph:

1. **No cycles.** If Course A is registered as equivalent to Course B, and B to C, the system must never allow C to be registered back as equivalent to A. That would create a logical loop with no real answer to "what does this course actually equal?" Cycle detection has to work for chains of any length, not just simple two- or three-course loops.

2. **No silent contradictions.** Two different official resolutions might, whether by mistake or overlapping authority, define incompatible outcomes for the same pair of courses in the same direction. The system must catch this and force a human to explicitly decide which resolution wins — the losing one gets marked as superseded, but never silently discarded or overwritten.

Everything else in the system — forms, file uploads, catalogs — exists in service of protecting these two guarantees.

## What the system actually does, end to end

- **Stores study plans** per academic program: their levels, the courses in each level, prerequisite relationships between courses, and whether the plan is currently active or being phased out (terminal).
- **Registers equivalencies** between courses across two different plans, each backed by a mandatory resolution document, with a direction of application (old→new, new→old, or bidirectional). Every new equivalency is checked against the existing graph for cycles and contradictions before it's allowed to save.
- **Applies informational accreditation**: once an equivalency is valid, students who passed the source course automatically get the target course marked as accredited in a simplified internal record — strictly in the approved direction, never the reverse.
- **Manages teaching modalities** (in-person, hybrid, virtual, tutoring, remote) as a catalog, tracks which modalities require an official resolution to use, and blocks assigning a resolution-required modality to a course unless a valid resolution is on file.

## Who uses it

- **Program Directors / Teaching Coordinators** — create study plans, register equivalencies, resolve contradictions between resolutions, manage course modalities.
- **Administrators** — maintain the modality catalog and resolve conflicts that require designating which resolution prevails.
- Test/simulated student records are used to demonstrate the accreditation behavior — this system does not touch or replace any official student transcript system.

## Why this matters beyond "it's a school assignment"

This mirrors a real, common institutional failure mode: critical decisions encoded as tribal knowledge instead of as data with rules and validation. The interesting engineering problem is exactly the kind of thing that shows up in real systems — modeling a domain as a graph, enforcing invariants (acyclicity, non-contradiction) at write time rather than trusting operators to catch mistakes, and keeping business rules independent of whatever web framework happens to be rendering the UI. Getting the plumbing (forms, auth, styling) right is necessary but not the point — the point is whether the domain logic actually holds up under adversarial or messy real-world input.