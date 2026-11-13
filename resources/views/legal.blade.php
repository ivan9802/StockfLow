@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Mentions légales et données personnelles')

@php $company = config('stockflow.company'); @endphp

@section('content')
    <div @class(['guest-card guest-wide' => ! auth()->check(), 'card card-body form-narrow' => auth()->check()])>
        <h1 class="mb-3">Mentions légales et données personnelles</h1>

        <div class="prose">
            <h2>Éditeur de l'application</h2>
            <p>
                StockFlow est un outil interne de gestion de stock exploité par {{ $company['name'] }},
                {{ $company['address'] }}. Registre du commerce : {{ $company['rccm'] }}.
                Responsable de la publication : {{ $company['publisher'] }}. Contact : {{ $company['email'] }}.
            </p>
            <p>Hébergement : {{ $company['host'] }}.</p>

            <h2>Accès</h2>
            <p>
                L'application est réservée au personnel habilité. Aucun compte ne peut être créé librement :
                les comptes sont ouverts par un administrateur.
            </p>

            <h2>Données personnelles traitées</h2>
            <p>
                Le traitement relève de la loi ivoirienne n° 2013-450 du 19 juin 2013 relative à la protection
                des données à caractère personnel. Le responsable du traitement est {{ $company['name'] }}.
            </p>
            <ul>
                <li>Comptes du personnel : nom, adresse e-mail professionnelle, profil, emplacement d'affectation, date de dernière connexion. Le mot de passe est conservé sous forme hachée et n'est lisible par personne.</li>
                <li>Journal d'activité : auteur, date, nature de chaque opération sur le stock, adresse IP et navigateur du poste. Il sert au contrôle interne et à la traçabilité comptable.</li>
                <li>Ventes : le nom du client, quand le vendeur choisit de le saisir. Ce champ est facultatif.</li>
                <li>Fournisseurs : nom, e-mail et téléphone de l'interlocuteur commercial.</li>
            </ul>
            <p>
                Ces données ne sont ni vendues ni transmises à des tiers. Elles sont conservées le temps de la
                relation de travail ou commerciale, puis selon les durées légales de conservation des pièces comptables.
            </p>

            <h2>Vos droits</h2>
            <p>
                Vous pouvez demander l'accès à vos données, leur rectification ou leur suppression en écrivant à
                {{ $company['email'] }}. Les opérations de stock déjà enregistrées restent attachées à leur auteur,
                car la traçabilité est une obligation de contrôle. Une réclamation peut être portée devant l'ARTCI.
            </p>

            <h2>Cookies</h2>
            <p>
                L'application dépose deux cookies techniques : le cookie de session, nécessaire à la connexion,
                et un jeton de protection des formulaires. Elle n'utilise aucun cookie publicitaire ni outil de mesure d'audience.
            </p>
        </div>

        @guest
            <p class="mt-3 mb-0"><a href="{{ route('login') }}">Retour à la connexion</a></p>
        @endguest
    </div>
@endsection
