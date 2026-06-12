<?php
// ── Language detection ──────────────────────────────────────────────────────
$_LANG = $_COOKIE['chicbook_lang'] ?? 'fr';
if (!in_array($_LANG, ['fr','en','es'])) $_LANG = 'fr';

// ── Translations ────────────────────────────────────────────────────────────
$_TRANSLATIONS = [

// ── Navigation ──────────────────────────────────────────────────────────────
'nav.home'          => ['fr'=>'Accueil',        'en'=>'Home',       'es'=>'Inicio'],
'nav.talents'       => ['fr'=>'Trouver un talent','en'=>'Find talent','es'=>'Buscar talento'],
'nav.talents_short' => ['fr'=>'Talents',           'en'=>'Talents',    'es'=>'Talentos'],
'nav.search'        => ['fr'=>'Recherche',      'en'=>'Search',     'es'=>'Buscar'],
'nav.castings'      => ['fr'=>'Castings',       'en'=>'Castings',   'es'=>'Castings'],
'nav.messages'      => ['fr'=>'Messagerie',     'en'=>'Messages',   'es'=>'Mensajes'],
'nav.events'        => ['fr'=>'Événements',     'en'=>'Events',     'es'=>'Eventos'],
'nav.preferences'   => ['fr'=>'Plus',           'en'=>'More',       'es'=>'Más'],
'nav.profile'       => ['fr'=>'Mon Profil',     'en'=>'My Profile', 'es'=>'Mi perfil'],
'nav.login'         => ['fr'=>"S'identifier",   'en'=>'Log in',     'es'=>'Iniciar sesión'],
'nav.backoffice'    => ['fr'=>'Back Office',    'en'=>'Back Office','es'=>'Back Office'],

// ── Auth ────────────────────────────────────────────────────────────────────
'auth.login_title'      => ['fr'=>'Bon retour parmi nous',      'en'=>'Welcome back',               'es'=>'Bienvenido de nuevo'],
'auth.login_subtitle'   => ['fr'=>'Connectez-vous pour accéder à votre espace talent ou recruteur.','en'=>'Log in to access your talent or recruiter space.','es'=>'Inicia sesión para acceder a tu espacio.'],
'auth.login_btn'        => ['fr'=>'Se connecter',   'en'=>'Log in',     'es'=>'Iniciar sesión'],
'auth.no_account'       => ['fr'=>"Pas encore de compte ?", 'en'=>'No account yet?','es'=>'¿Sin cuenta aún?'],
'auth.register'         => ['fr'=>"S'inscrire",     'en'=>'Sign up',    'es'=>'Registrarse'],
'auth.forgot'           => ['fr'=>'Mot de passe oublié ?','en'=>'Forgot password?','es'=>'¿Olvidaste tu contraseña?'],
'auth.email'            => ['fr'=>'Adresse email',  'en'=>'Email address','es'=>'Correo electrónico'],
'auth.password'         => ['fr'=>'Mot de passe',   'en'=>'Password',   'es'=>'Contraseña'],

// ── Inscription ─────────────────────────────────────────────────────────────
'register.title'        => ['fr'=>'Rejoindre ChicBook',     'en'=>'Join ChicBook',          'es'=>'Únete a ChicBook'],
'register.subtitle'     => ['fr'=>'Créez votre profil talent et rejoignez le réseau','en'=>'Create your talent profile and join the network','es'=>'Crea tu perfil de talento y únete a la red'],
'register.btn'          => ['fr'=>'Créer mon compte',       'en'=>'Create my account',      'es'=>'Crear mi cuenta'],
'register.gender'       => ['fr'=>'Genre',                  'en'=>'Gender',                 'es'=>'Género'],
'register.firstname'    => ['fr'=>'Prénom',                 'en'=>'First name',             'es'=>'Nombre'],
'register.lastname'     => ['fr'=>'Nom',                    'en'=>'Last name',              'es'=>'Apellido'],
'register.birthdate'    => ['fr'=>'Date de naissance',      'en'=>'Date of birth',          'es'=>'Fecha de nacimiento'],
'register.language'     => ['fr'=>'Langues parlées',        'en'=>'Languages spoken',       'es'=>'Idiomas hablados'],
'register.city'         => ['fr'=>'Ville',                  'en'=>'City',                   'es'=>'Ciudad'],
'register.country'      => ['fr'=>'Pays',                   'en'=>'Country',                'es'=>'País'],
'register.job'          => ['fr'=>'Votre métier',           'en'=>'Your profession',        'es'=>'Tu profesión'],
'register.tags_label'   => ['fr'=>'Mots-clés qui vous décrivent','en'=>'Keywords that describe you','es'=>'Palabras clave que te describen'],
'register.tags_hint'    => ['fr'=>'(sélectionnez ceux qui vous correspondent)','en'=>'(select those that apply)','es'=>'(selecciona los que correspondan)'],
'register.measurements' => ['fr'=>'Vos mensurations',       'en'=>'Your measurements',      'es'=>'Tus medidas'],
'register.already'      => ['fr'=>'Déjà un compte ?',       'en'=>'Already have an account?','es'=>'¿Ya tienes cuenta?'],
'register.female'       => ['fr'=>'Femme',  'en'=>'Female', 'es'=>'Mujer'],
'register.male'         => ['fr'=>'Homme',  'en'=>'Male',   'es'=>'Hombre'],
'register.nonbinary'    => ['fr'=>'Non-binaire','en'=>'Non-binary','es'=>'No binario'],

// ── Feed / Index ────────────────────────────────────────────────────────────
'feed.filter_label'     => ['fr'=>'Fil d\'actualité',       'en'=>'Feed',                   'es'=>'Noticias'],
'feed.filter_all'       => ['fr'=>'Tous les talents',       'en'=>'All talents',            'es'=>'Todos los talentos'],
'feed.join_title'       => ['fr'=>'Rejoindre ChicBook',     'en'=>'Join ChicBook',          'es'=>'Únete a ChicBook'],
'feed.join_cta'         => ['fr'=>"S'inscrire gratuitement",'en'=>'Sign up for free',       'es'=>'Regístrate gratis'],
'feed.upcoming_events'  => ['fr'=>'Événements à venir',     'en'=>'Upcoming events',        'es'=>'Próximos eventos'],
'feed.fashion_movement' => ['fr'=>'La mode en mouvement',   'en'=>'Fashion in motion',      'es'=>'La moda en movimiento'],

// ── Profile ─────────────────────────────────────────────────────────────────
'profile.photos'        => ['fr'=>'Photos',         'en'=>'Photos',         'es'=>'Fotos'],
'profile.edit'          => ['fr'=>'Modifier le profil','en'=>'Edit profile','es'=>'Editar perfil'],
'profile.follow'        => ['fr'=>'Suivre',          'en'=>'Follow',         'es'=>'Seguir'],
'profile.following'     => ['fr'=>'Suivi ✓',         'en'=>'Following ✓',    'es'=>'Siguiendo ✓'],
'profile.contact'       => ['fr'=>'Contacter',       'en'=>'Contact',        'es'=>'Contactar'],
'profile.bio'           => ['fr'=>'Biographie',      'en'=>'Biography',      'es'=>'Biografía'],
'profile.share'         => ['fr'=>'Partager',        'en'=>'Share',          'es'=>'Compartir'],
'profile.measurements'  => ['fr'=>'Mensurations',    'en'=>'Measurements',   'es'=>'Medidas'],
'profile.new_project'   => ['fr'=>'＋ Nouveau projet','en'=>'＋ New project', 'es'=>'＋ Nuevo proyecto'],
'profile.projects'      => ['fr'=>'Projets',         'en'=>'Projects',       'es'=>'Proyectos'],

// ── Castings ────────────────────────────────────────────────────────────────
'castings.opportunities'=> ['fr'=>'Opportunités',    'en'=>'Opportunities',  'es'=>'Oportunidades'],
'castings.favorites'    => ['fr'=>'♡ Favoris',       'en'=>'♡ Favorites',    'es'=>'♡ Favoritos'],
'castings.mine'         => ['fr'=>'Mes castings',    'en'=>'My castings',    'es'=>'Mis castings'],
'castings.create'       => ['fr'=>'＋ Créer',         'en'=>'＋ Create',       'es'=>'＋ Crear'],
'castings.filters'      => ['fr'=>'Filtres',         'en'=>'Filters',        'es'=>'Filtros'],
'castings.apply_filters'=> ['fr'=>'Appliquer les filtres','en'=>'Apply filters','es'=>'Aplicar filtros'],
'castings.filter_date'  => ['fr'=>'Date de prestation','en'=>'Performance date','es'=>'Fecha de actuación'],

// ── Events ──────────────────────────────────────────────────────────────────
'events.all'            => ['fr'=>'Tous',            'en'=>'All',            'es'=>'Todos'],
'events.upcoming'       => ['fr'=>'À venir',         'en'=>'Upcoming',       'es'=>'Próximos'],
'events.my_registrations'=>['fr'=>'Mes inscriptions','en'=>'My registrations','es'=>'Mis inscripciones'],
'events.my_events'      => ['fr'=>'Mes événements',  'en'=>'My events',      'es'=>'Mis eventos'],
'events.interested'     => ['fr'=>"Je suis intéressé(e)",'en'=>"I'm interested",'es'=>'Me interesa'],
'events.create'         => ['fr'=>'＋ Proposer un événement','en'=>'＋ Propose an event','es'=>'＋ Proponer evento'],
'events.create_short'   => ['fr'=>'+ Proposer',      'en'=>'+ Propose',      'es'=>'+ Proponer'],
'events.filters'        => ['fr'=>'Filtres',         'en'=>'Filters',        'es'=>'Filtros'],
'events.apply_filters'  => ['fr'=>'Appliquer les filtres','en'=>'Apply filters','es'=>'Aplicar filtros'],
'events.filter_type'    => ['fr'=>'Type',            'en'=>'Type',           'es'=>'Tipo'],
'events.all_types'      => ['fr'=>'Tous les types',  'en'=>'All types',      'es'=>'Todos los tipos'],

// ── Preferences ─────────────────────────────────────────────────────────────
'prefs.title'           => ['fr'=>'Préférences',     'en'=>'Preferences',    'es'=>'Preferencias'],
'prefs.appearance'      => ['fr'=>'Apparence',       'en'=>'Appearance',     'es'=>'Apariencia'],
'prefs.dark_mode'       => ['fr'=>'Mode sombre',     'en'=>'Dark mode',      'es'=>'Modo oscuro'],
'prefs.theme_label'     => ['fr'=>"Thème de l'interface",'en'=>'Interface theme','es'=>'Tema de la interfaz'],
'prefs.theme_light_on'  => ['fr'=>'Thème clair activé','en'=>'Light theme active','es'=>'Tema claro activo'],
'prefs.theme_dark_on'   => ['fr'=>'Thème sombre activé','en'=>'Dark theme active','es'=>'Tema oscuro activo'],
'prefs.language'        => ['fr'=>'Langue',          'en'=>'Language',       'es'=>'Idioma'],
'prefs.report'          => ['fr'=>'Signaler un problème','en'=>'Report an issue','es'=>'Reportar un problema'],
'prefs.report_category' => ['fr'=>'Catégorie',       'en'=>'Category',       'es'=>'Categoría'],
'prefs.report_description'=>['fr'=>'Description',    'en'=>'Description',    'es'=>'Descripción'],
'prefs.report_send'     => ['fr'=>'Envoyer le signalement','en'=>'Send report','es'=>'Enviar reporte'],
'prefs.suggest'         => ['fr'=>"Suggérer une amélioration",'en'=>'Suggest an improvement','es'=>'Sugerir una mejora'],
'prefs.suggest_label'   => ['fr'=>'Votre idée',      'en'=>'Your idea',      'es'=>'Tu idea'],
'prefs.suggest_send'    => ['fr'=>'Envoyer ma suggestion','en'=>'Send my suggestion','es'=>'Enviar sugerencia'],
'prefs.logout'          => ['fr'=>'Se déconnecter',  'en'=>'Log out',        'es'=>'Cerrar sesión'],
'prefs.logout_desc'     => ['fr'=>"Vous serez redirigé vers la page d'accueil.",'en'=>'You will be redirected to the home page.','es'=>'Serás redirigido a la página de inicio.'],
'prefs.account'         => ['fr'=>'Compte',          'en'=>'Account',        'es'=>'Cuenta'],
'prefs.lang_fr'         => ['fr'=>'Français',        'en'=>'French',         'es'=>'Francés'],
'prefs.lang_en'         => ['fr'=>'Anglais',         'en'=>'English',        'es'=>'Inglés'],
'prefs.lang_es'         => ['fr'=>'Espagnol',        'en'=>'Spanish',        'es'=>'Español'],

// ── Talents (trouver_talent) ─────────────────────────────────────────────────
'talents.search_placeholder' => ['fr'=>'Rechercher un talent…','en'=>'Search a talent…','es'=>'Buscar un talento…'],
'talents.filter_keyword'     => ['fr'=>'Mot-clé',              'en'=>'Keyword',           'es'=>'Palabra clave'],
'talents.all_tags'           => ['fr'=>'Tous les tags',         'en'=>'All tags',          'es'=>'Todos los tags'],
'talents.all_countries'      => ['fr'=>'Tous les pays',         'en'=>'All countries',     'es'=>'Todos los países'],

// ── Common ───────────────────────────────────────────────────────────────────
'common.save'           => ['fr'=>'Enregistrer',     'en'=>'Save',           'es'=>'Guardar'],
'common.cancel'         => ['fr'=>'Annuler',         'en'=>'Cancel',         'es'=>'Cancelar'],
'common.delete'         => ['fr'=>'Supprimer',       'en'=>'Delete',         'es'=>'Eliminar'],
'common.edit'           => ['fr'=>'Modifier',        'en'=>'Edit',           'es'=>'Editar'],
'common.close'          => ['fr'=>'Fermer',          'en'=>'Close',          'es'=>'Cerrar'],
'common.send'           => ['fr'=>'Envoyer',         'en'=>'Send',           'es'=>'Enviar'],
'common.filters'        => ['fr'=>'Filtres',         'en'=>'Filters',        'es'=>'Filtros'],
'common.search'         => ['fr'=>'Rechercher',      'en'=>'Search',         'es'=>'Buscar'],
'common.loading'        => ['fr'=>'Chargement…',     'en'=>'Loading…',       'es'=>'Cargando…'],
'common.see_more'       => ['fr'=>'Voir plus',       'en'=>'See more',       'es'=>'Ver más'],
'common.see_less'       => ['fr'=>'Voir moins',      'en'=>'See less',       'es'=>'Ver menos'],
'common.back'           => ['fr'=>'Retour',          'en'=>'Back',           'es'=>'Volver'],
'common.all'            => ['fr'=>'Tous',            'en'=>'All',            'es'=>'Todos'],
];

function t(string $key): string {
    global $_TRANSLATIONS, $_LANG;
    if (!isset($_TRANSLATIONS[$key])) return $key;
    return $_TRANSLATIONS[$key][$_LANG] ?? $_TRANSLATIONS[$key]['fr'] ?? $key;
}
