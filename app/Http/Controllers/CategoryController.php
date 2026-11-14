<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('categories.form', ['category' => new Category]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', "La catégorie {$category->name} a été créée.");
    }

    public function edit(Category $category): View
    {
        return view('categories.form', ['category' => $category]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        // Le code sert de préfixe aux références déjà attribuées : il ne change plus.
        $category->update($request->safe()->except('code'));

        return redirect()->route('categories.index')->with('success', "La catégorie {$category->name} a été modifiée.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Les produits retirés du catalogue comptent aussi : leur historique garde la catégorie.
        $count = Product::withTrashed()->where('category_id', $category->id)->count();

        if ($count > 0) {
            throw new BusinessException(sprintf(
                'La catégorie %s contient %d produit%s. Déplacez-les dans une autre catégorie avant de la supprimer.',
                $category->name, $count, $count > 1 ? 's' : ''
            ));
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', "La catégorie {$category->name} a été supprimée.");
    }
}
