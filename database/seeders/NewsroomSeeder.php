<?php

namespace Database\Seeders;

use App\Enums\NewsCategory;
use App\Models\NewsItem;
use App\Models\Sector;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Six demonstration articles. EVERY ROW IS is_sample = true.
 *
 * None of these is a real CRASA announcement. They are shaped like the
 * items a regional regulator association publishes so the listing, the
 * filters and the card grid can be judged on real proportions, and each
 * says in its own body that it is demonstration content. No date, decision,
 * figure or quotation is asserted.
 *
 * Categories and sectors are spread deliberately: four different sectors
 * means the fallback image treatment differs card to card, which is what
 * stops the grid reading as generated.
 */
class NewsroomSeeder extends Seeder
{
    /** @var list<array{slug: string, category: string, sector: string|null, tags: list<string>, en: array<string,string>, fr: array<string,string>, pt: array<string,string>}> */
    private const ARTICLES = [
        [
            'slug' => 'spectrum-committee-ninth-meeting',
            'category' => 'news',
            'sector' => 'telecommunications',
            'tags' => ['spectrum', 'committees'],
            'en' => ['title' => 'Spectrum and Frequency Management Committee holds its ninth meeting', 'summary' => 'The committee reviewed progress on the regional spectrum allocation plan.'],
            'fr' => ['title' => 'Le Comité de gestion du spectre et des fréquences tient sa neuvième réunion', 'summary' => "Le comité a examiné l'avancement du plan régional d'attribution des fréquences."],
            'pt' => ['title' => 'Comité de Gestão do Espectro e das Frequências realiza a sua nona reunião', 'summary' => 'O comité analisou o progresso do plano regional de atribuição de espectro.'],
        ],
        [
            'slug' => 'consultation-roaming-guidelines',
            'category' => 'consultation',
            'sector' => 'telecommunications',
            'tags' => ['roaming', 'consultation'],
            'en' => ['title' => 'Public consultation on the regional roaming guidelines', 'summary' => 'Member regulators, operators and consumer bodies are invited to comment.'],
            'fr' => ['title' => 'Consultation publique sur les lignes directrices régionales en matière d\'itinérance', 'summary' => 'Les régulateurs membres, les opérateurs et les associations de consommateurs sont invités à commenter.'],
            'pt' => ['title' => 'Consulta pública sobre as diretrizes regionais de roaming', 'summary' => 'Os reguladores membros, operadores e associações de consumidores são convidados a comentar.'],
        ],
        [
            'slug' => 'postal-committee-universal-service',
            'category' => 'news',
            'sector' => 'postal',
            'tags' => ['postal', 'committees'],
            'en' => ['title' => 'Postal and Logistics Committee reviews universal service obligations', 'summary' => 'Work continues on quality of service monitoring across the region.'],
            'fr' => ['title' => 'Le Comité postal et logistique examine les obligations de service universel', 'summary' => 'Les travaux se poursuivent sur le suivi de la qualité de service dans la région.'],
            'pt' => ['title' => 'Comité Postal e de Logística analisa as obrigações de serviço universal', 'summary' => 'Prossegue o trabalho sobre a monitorização da qualidade do serviço na região.'],
        ],
        [
            'slug' => 'tender-platform-maintenance',
            'category' => 'tender',
            'sector' => null,
            'tags' => ['procurement'],
            'en' => ['title' => 'Tender notice: platform maintenance and support', 'summary' => 'The Secretariat invites bids for ongoing maintenance of its digital platforms.'],
            'fr' => ['title' => "Avis d'appel d'offres : maintenance et assistance de la plateforme", 'summary' => 'Le Secrétariat lance un appel à candidatures pour la maintenance de ses plateformes numériques.'],
            'pt' => ['title' => 'Anúncio de concurso: manutenção e apoio da plataforma', 'summary' => 'O Secretariado convida a apresentação de propostas para a manutenção das suas plataformas digitais.'],
        ],
        [
            'slug' => 'broadcasting-digital-migration-progress',
            'category' => 'news',
            'sector' => 'broadcasting',
            'tags' => ['broadcasting'],
            'en' => ['title' => 'Digital terrestrial television migration progress across the region', 'summary' => 'Member states report on the state of their migration programmes.'],
            'fr' => ['title' => 'Avancement de la migration vers la télévision numérique terrestre dans la région', 'summary' => "Les États membres rendent compte de l'état de leurs programmes de migration."],
            'pt' => ['title' => 'Progresso da migração para a televisão digital terrestre na região', 'summary' => 'Os Estados membros informam sobre o estado dos seus programas de migração.'],
        ],
        [
            'slug' => 'communique-annual-general-meeting',
            'category' => 'communique',
            'sector' => null,
            'tags' => ['governance'],
            'en' => ['title' => 'Communique of the Annual General Meeting', 'summary' => 'The membership met to review the work programme for the coming cycle.'],
            'fr' => ['title' => "Communiqué de l'Assemblée générale annuelle", 'summary' => 'Les membres se sont réunis pour examiner le programme de travail du prochain cycle.'],
            'pt' => ['title' => 'Comunicado da Assembleia Geral Anual', 'summary' => 'Os membros reuniram-se para analisar o programa de trabalho do próximo ciclo.'],
        ],
    ];

    /** @var array<string, array{en: string, fr: string, pt: string}> */
    private const TAGS = [
        'spectrum' => ['en' => 'Spectrum', 'fr' => 'Spectre', 'pt' => 'Espectro'],
        'roaming' => ['en' => 'Roaming', 'fr' => 'Itinérance', 'pt' => 'Roaming'],
        'postal' => ['en' => 'Postal', 'fr' => 'Postal', 'pt' => 'Postal'],
        'broadcasting' => ['en' => 'Broadcasting', 'fr' => 'Radiodiffusion', 'pt' => 'Radiodifusão'],
        'committees' => ['en' => 'Committees', 'fr' => 'Comités', 'pt' => 'Comités'],
        'consultation' => ['en' => 'Consultation', 'fr' => 'Consultation', 'pt' => 'Consulta'],
        'procurement' => ['en' => 'Procurement', 'fr' => 'Marchés publics', 'pt' => 'Contratação'],
        'governance' => ['en' => 'Governance', 'fr' => 'Gouvernance', 'pt' => 'Governação'],
    ];

    private const DEMO_BODY = [
        'en' => "This is demonstration content. It is not a CRASA announcement, and nothing in it should be cited or relied on.\n\nThe article exists so the newsroom listing, the filters and the article layout can be reviewed with realistic proportions before the Secretariat supplies real copy. It will be removed with all other sample data before go-live.",
        'fr' => "Ceci est un contenu de démonstration. Il ne s'agit pas d'une communication de la CRASA et rien de ce qui suit ne doit être cité ni utilisé comme référence.\n\nCet article existe pour que la salle de presse, les filtres et la mise en page puissent être examinés avec des proportions réalistes avant que le Secrétariat ne fournisse le texte définitif. Il sera supprimé avec toutes les autres données de démonstration avant la mise en service.",
        'pt' => "Este é um conteúdo de demonstração. Não é um comunicado da CRASA e nada aqui deve ser citado ou utilizado como referência.\n\nO artigo existe para que a sala de imprensa, os filtros e a disposição do artigo possam ser avaliados com proporções realistas antes de o Secretariado fornecer o texto definitivo. Será removido com todos os outros dados de demonstração antes da entrada em serviço.",
    ];

    public function run(): void
    {
        $sectors = Sector::pluck('id', 'slug');

        $tags = [];
        foreach (self::TAGS as $slug => $names) {
            $tag = Tag::updateOrCreate(['slug' => $slug], ['is_sample' => true]);
            foreach ($names as $locale => $name) {
                $tag->setTranslation('name', $locale, $name);
            }
            $tags[$slug] = $tag->id;
        }

        foreach (self::ARTICLES as $index => $article) {
            $item = NewsItem::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'category' => NewsCategory::from($article['category']),
                    'sector_id' => $article['sector'] === null ? null : ($sectors[$article['sector']] ?? null),
                    'published_at' => now()->subDays(($index + 1) * 9),
                    'author_name' => 'CRASA Secretariat',
                    'status' => 'published',
                    'is_sample' => true,
                ]
            );

            foreach (['en', 'fr', 'pt'] as $locale) {
                $item->setTranslations($locale, [
                    'title' => $article[$locale]['title'],
                    'summary' => $article[$locale]['summary'],
                    'body' => self::DEMO_BODY[$locale],
                ]);
            }

            $item->tags()->sync(array_map(fn (string $slug) => $tags[$slug], $article['tags']));
        }
    }
}
