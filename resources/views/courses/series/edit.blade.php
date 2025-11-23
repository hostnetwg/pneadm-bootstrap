<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edycja serii: ') }} {{ $series->name }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('courses.series.update', $series) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Nazwa serii</label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $series->name) }}" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Opis (opcjonalnie)</label>
                    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $series->description) }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="sort_order" class="form-label">Kolejność wyświetlania</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', $series->sort_order) }}">
                    </div>
                    
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $series->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Seria aktywna</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Obrazek (opcjonalnie)</label>
                    <input type="file" name="image" class="form-control" id="image" accept="image/*">
                    <div class="form-text">Dozwolone formaty: JPEG, PNG, JPG, GIF (max 2MB)</div>
                    
                    @if ($series->image)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $series->image) }}" alt="Obrazek serii" class="img-thumbnail" style="max-width: 200px;">
                            
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image">
                                <label class="form-check-label" for="remove_image">Usuń obrazek</label>
                            </div>
                        </div>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                <a href="{{ route('courses.series.index') }}" class="btn btn-secondary">Anuluj</a>
            </form>
        </div>
    </div>
</x-app-layout>
