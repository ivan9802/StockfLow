@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <x-page-header title="Tableau de bord" />
    <x-empty-state icon="speedometer2" title="Le tableau de bord arrive avec les indicateurs de stock." />
@endsection
