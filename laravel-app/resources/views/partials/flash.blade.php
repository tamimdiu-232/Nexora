{{-- Success / info messages and validation errors --}}
@if(session('success'))<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div>{{ session('success') }}</div></div>@endif
@if(session('status'))<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i><div>{{ session('status') }}</div></div>@endif
@if($errors->any())
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong>Please check the following</strong><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif
