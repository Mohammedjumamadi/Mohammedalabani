@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.content'))

@php
    $sections = ['faq' => [$faq, 'admin.content.faq'], 'awareness' => [$awareness, 'admin.content.awareness']];
@endphp

@section('content')
    <div class="page-head"><h1>{{ __('nav.admin.content') }}</h1></div>
    <p class="text-muted">{{ __('admin.content.help') }}</p>

    @foreach ($sections as $key => [$items, $titleKey])
        <section id="{{ $key }}" class="aimv-card mb-4" aria-labelledby="h-{{ $key }}"
                 x-data="{ items: @js(count($items) ? $items : []) , add() { this.items.push({ title: { ar: '', en: '' }, body: { ar: '', en: '' } }) }, remove(i) { this.items.splice(i, 1) } }">
            <div class="card-head"><h2 id="h-{{ $key }}">{{ __($titleKey) }}</h2>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="add()"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('admin.content.add') }}</button></div>
            <form method="POST" action="{{ route('admin.content.update', $key) }}" class="card-body-pad">
                @csrf @method('PUT')
                <template x-for="(item, i) in items" :key="i">
                    <fieldset class="border rounded-3 p-3 mb-3">
                        <legend class="float-none w-auto fs-6 px-2"><span x-text="i + 1"></span></legend>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label small">{{ __('admin.content.title_ar') }}</label><input class="form-control" :name="`items[${i}][title][ar]`" x-model="item.title.ar" maxlength="200"></div>
                            <div class="col-md-6"><label class="form-label small">{{ __('admin.content.title_en') }}</label><input class="form-control" dir="ltr" :name="`items[${i}][title][en]`" x-model="item.title.en" maxlength="200"></div>
                            <div class="col-md-6"><label class="form-label small">{{ __('admin.content.body_ar') }}</label><textarea class="form-control" rows="4" :name="`items[${i}][body][ar]`" x-model="item.body.ar" maxlength="5000"></textarea></div>
                            <div class="col-md-6"><label class="form-label small">{{ __('admin.content.body_en') }}</label><textarea class="form-control" dir="ltr" rows="4" :name="`items[${i}][body][en]`" x-model="item.body.en" maxlength="5000"></textarea></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="remove(i)"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('common.delete') }}</button>
                    </fieldset>
                </template>
                <p class="text-muted" x-show="items.length === 0">{{ __('common.no_data') }}</p>
                <button class="btn btn-primary"><i class="bi bi-save" aria-hidden="true"></i> {{ __('common.save') }}</button>
            </form>
        </section>
    @endforeach
@endsection
