@extends('errors::minimal')

@section('title', __('Not authorized'))
@section('code', '403')
@section('message', __($exception->getMessage() ?: 'You are not authorized to access this area.'))
