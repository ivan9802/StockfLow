@extends('layouts.app')

@section('title', 'Emplacements')

@section('content')
    <x-page-header title="Emplacements" />

    <div class="table-card">
        <table class="table data-table">
            <caption class="visually-hidden">Emplacements de stockage</caption>
            <thead>
                <tr>
                    <th scope="col">Emplacement</th><th scope="col">Code</th><th scope="col">Type</th>
                    <th scope="col" class="col-p2">Adresse</th><th scope="col" class="num">Unités</th><th scope="col" class="num col-p3">Références en stock</th>
                    <th scope="col" class="actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($warehouses as $warehouse)
                    <tr>
                        <td class="fw-semibold">{{ $warehouse->name }}</td>
                        <td><span class="code">{{ $warehouse->code }}</span></td>
                        <td>{{ $warehouse->type->label() }}</td>
                        <td class="col-p2">{{ $warehouse->address }}</td>
                        <td class="num">{{ Format::number($warehouse->units) }}</td>
                        <td class="num col-p3">{{ Format::number($warehouse->references) }}</td>
                        <td class="actions"><a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouses.edit', $warehouse) }}">Modifier<span class="visually-hidden"> {{ $warehouse->name }}</span></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-secondary small mt-3">Les deux emplacements de l'entreprise sont fixes dans cette version : leur nom et leur adresse se modifient, ils ne se créent ni ne se suppriment.</p>
@endsection
