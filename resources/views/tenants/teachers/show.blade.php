@extends('tenants.layouts.app')

@section('content')

    <pending-teachers
            :teachers="{{ $teachers }}"
            :jobs="{{ $jobs }}"
            :pending-teachers="{{ $pendingTeachers }}"
            :specialties="{{ $specialties }}"
            :forces="{{ $forces }}"
            :administrative-statuses="{{ $administrativeStatuses }}">
    </pending-teachers>

    <teachers :teachers="{{ $teachers }}" :administrative-statuses="{{ $administrativeStatuses }}"></teachers>



@endsection


