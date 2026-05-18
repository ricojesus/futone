<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(): View
    {
        $countries = Country::withCount('players')->orderBy('name')->paginate(30);

        return view('admin.countries.index', compact('countries'));
    }

    public function create(): View
    {
        return view('admin.countries.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:countries,name'],
            'code' => ['required', 'string', 'max:3', 'uppercase', 'unique:countries,code'],
            'flag' => ['nullable', 'string', 'max:10'],
        ]);

        Country::create($request->only('name', 'code', 'flag'));

        return redirect()->route('admin.countries')->with('success', 'País criado com sucesso.');
    }

    public function edit(Country $country): View
    {
        return view('admin.countries.edit', compact('country'));
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:countries,name,' . $country->id],
            'code' => ['required', 'string', 'max:3', 'uppercase', 'unique:countries,code,' . $country->id],
            'flag' => ['nullable', 'string', 'max:10'],
        ]);

        $country->update($request->only('name', 'code', 'flag'));

        return redirect()->route('admin.countries')->with('success', 'País atualizado com sucesso.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        $country->delete();

        return redirect()->route('admin.countries')->with('success', 'País removido.');
    }
}
