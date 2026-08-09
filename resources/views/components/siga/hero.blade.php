<section class="py-12 md:py-18 relative z-10 text-center lg:text-left">
    <div class="flex items-center justify-center lg:justify-start gap-2.5 mb-6">
        <span class="w-[34px] h-[2px] bg-background-brand-default"></span>
        <span class="text-[13px] font-bold text-text-brand tracking-[0.6px] uppercase">{{ __('NATIONAL TECHNICAL UNIVERSITY') }}</span>
    </div>

    <h1 class="font-black text-4xl lg:text-[52px] leading-[1.12] text-text-primary mb-5">
        {!! nl2br(e(__('Comprehensive Academic Management System'))) !!}
    </h1>

    <p class="text-[17px] leading-relaxed text-text-secondary max-w-[480px] mb-10 mx-auto lg:mx-0">
        {{ __('Centralizes study plans, physical spaces, teaching pertinence, student requests, and reports for the Regional Campus San Carlos in one place, traceable and always updated.') }}
    </p>

    <div class="flex flex-wrap border border-border-default rounded-xl overflow-hidden w-fit bg-background-surface mx-auto lg:mx-0">
        <div class="px-5 md:px-7 py-4 text-center">
            <div class="font-black text-2xl text-text-brand">5</div>
            <div class="text-xs text-text-secondary mt-0.5">{{ __('Modules') }}</div>
        </div>
        <div class="w-px bg-border-default"></div>
        <div class="px-5 md:px-7 py-4 text-center">
            <div class="font-black text-2xl text-text-brand">1</div>
            <div class="text-xs text-text-secondary mt-0.5">{{ __('Campus') }}</div>
        </div>
        <div class="w-px bg-border-default"></div>
        <div class="px-5 md:px-7 py-4 text-center">
            <div class="font-black text-2xl text-text-brand">2026</div>
            <div class="text-xs text-text-secondary mt-0.5">{{ __('Term II') }}</div>
        </div>
    </div>
</section>
