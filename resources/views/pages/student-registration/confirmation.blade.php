<x-registration-layout>
    <div class="mt-12 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success-soft text-success-ink">
            <x-icon name="check-circle" :size="32" />
        </div>

        <h1 class="mt-6 text-2xl font-semibold tracking-tight sm:text-3xl">Registration Submitted</h1>
        <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-muted">
            Your student registration has been submitted successfully.
            Your account is currently awaiting administrator approval.
        </p>

        <p class="mt-6 text-sm text-muted">
            Student ID: <span class="font-mono font-medium text-ink">{{ $maskedStudentId }}</span>
        </p>

        <div class="mt-10">
            <x-ui.button href="{{ route('home') }}">Back to Home</x-ui.button>
        </div>
    </div>
</x-registration-layout>
