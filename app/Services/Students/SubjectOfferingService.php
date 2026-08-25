<?php

namespace App\Services\Students;

use App\Models\Section;
use App\Models\Student;
use App\Models\SubjectOffering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubjectOfferingService
{
    /**
     * @return array{recommended: Collection<int, array<string, mixed>>, other: Collection<int, array<string, mixed>>}
     */
    public function offeringsForRegistration(
        int $sectionId,
        int $departmentId,
        ?string $search = null,
        bool $browseAll = false,
    ): array {
        $section = Section::query()->with(['program', 'yearLevel', 'academicYear', 'semester'])->findOrFail($sectionId);

        $baseQuery = $this->baseOfferingQuery($search)
            ->when($section->academic_year_id, fn (Builder $q) => $q->where('academic_year_id', $section->academic_year_id))
            ->when($section->semester_id, fn (Builder $q) => $q->where('semester_id', $section->semester_id));

        $recommended = (clone $baseQuery)
            ->where('section_id', $sectionId)
            ->orderBy('subject_id')
            ->orderBy('section_id')
            ->orderBy('instructor_id')
            ->get()
            ->map(fn (SubjectOffering $offering) => $this->formatOffering($offering));

        $otherQuery = (clone $baseQuery)->where('section_id', '!=', $sectionId);

        if (! $browseAll) {
            $otherQuery->whereHas('section.program', fn (Builder $q) => $q->where('department_id', $departmentId));
        }

        $other = $otherQuery
            ->orderBy('subject_id')
            ->orderBy('section_id')
            ->orderBy('instructor_id')
            ->limit(100)
            ->get()
            ->map(fn (SubjectOffering $offering) => $this->formatOffering($offering));

        return [
            'recommended' => $recommended->values(),
            'other' => $other->values(),
        ];
    }

    /**
     * @param  array<int, int|string>  $offeringIds
     * @return Collection<int, SubjectOffering>
     */
    public function validateOfferingIds(array $offeringIds): Collection
    {
        $ids = collect($offeringIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            abort(422, 'Please select at least one subject offering.');
        }

        $offerings = SubjectOffering::query()
            ->with(['subject', 'instructor.user', 'section.program', 'section.yearLevel', 'academicYear', 'semester'])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereNotNull('section_id')
            ->whereHas('subject', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('section', fn (Builder $q) => $q->where('is_active', true))
            ->get()
            ->keyBy('id');

        $invalid = $ids->diff($offerings->keys());

        if ($invalid->isNotEmpty()) {
            abort(422, 'One or more selected subject offerings are invalid or unavailable.');
        }

        return $ids->map(fn (int $id) => $offerings->get($id))->filter()->values();
    }

    public function find(int $offeringId): ?SubjectOffering
    {
        return SubjectOffering::query()
            ->with(['subject', 'instructor.user', 'section.program', 'section.yearLevel', 'academicYear', 'semester'])
            ->find($offeringId);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatOffering(SubjectOffering $offering): array
    {
        $offering->loadMissing(['subject', 'instructor.user', 'section.program', 'section.yearLevel', 'academicYear', 'semester']);

        return [
            'id' => $offering->id,
            'subject_id' => $offering->subject_id,
            'code' => $offering->subject?->code,
            'name' => $offering->subject?->name,
            'units' => $offering->subject?->units,
            'instructor_name' => $offering->instructorDisplayName(),
            'section_id' => $offering->section_id,
            'section_name' => $offering->sectionDisplayName(),
            'section_code' => $offering->section?->code,
            'program_name' => $offering->section?->program?->name,
            'year_level_name' => $offering->section?->yearLevel?->name,
            'academic_year_name' => $offering->academicYear?->name,
            'semester_name' => $offering->semester?->name,
            'academic_year_id' => $offering->academic_year_id,
            'semester_id' => $offering->semester_id,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function offeringsForChangeRequest(Student $student, int $academicYearId, int $semesterId): Collection
    {
        $enrolledOfferingIds = $student->subjectEnrollments()
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->pluck('subject_offering_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        return SubjectOffering::query()
            ->with(['subject', 'instructor.user', 'section.program', 'section.yearLevel', 'academicYear', 'semester'])
            ->where('is_active', true)
            ->whereNotNull('section_id')
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->whereHas('subject', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('section', fn (Builder $q) => $q->where('is_active', true))
            ->when($enrolledOfferingIds->isNotEmpty(), fn (Builder $q) => $q->whereNotIn('id', $enrolledOfferingIds))
            ->orderBy('subject_id')
            ->orderBy('section_id')
            ->get()
            ->map(fn (SubjectOffering $offering) => $this->formatOffering($offering));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function offeringsForExam(
        int $subjectId,
        int $academicYearId,
        int $semesterId,
        ?string $search = null,
    ): Collection {
        return $this->baseOfferingQuery($search)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->orderBy('section_id')
            ->orderBy('instructor_id')
            ->get()
            ->map(fn (SubjectOffering $offering) => $this->formatOffering($offering));
    }

    protected function baseOfferingQuery(?string $search = null): Builder
    {
        $query = SubjectOffering::query()
            ->with(['subject', 'instructor.user', 'section.program', 'section.yearLevel', 'academicYear', 'semester'])
            ->where('is_active', true)
            ->whereNotNull('section_id')
            ->whereHas('subject', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('section', fn (Builder $q) => $q->where('is_active', true));

        if (! filled($search)) {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $inner) use ($term) {
            $inner->whereHas('subject', function (Builder $subjectQuery) use ($term) {
                $subjectQuery->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            })->orWhereHas('section', function (Builder $sectionQuery) use ($term) {
                $sectionQuery->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            })->orWhereHas('instructor.user', function (Builder $userQuery) use ($term) {
                $userQuery->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        });
    }
}
