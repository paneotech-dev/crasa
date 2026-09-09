<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\PageBlock;
use App\Support\Locales;
use Illuminate\Database\Seeder;

/**
 * The three legal pages the footer links to. They must not 404.
 *
 * Privacy and terms are legal text and therefore client content: what
 * personal data CRASA processes, on what basis, for how long, and under
 * which jurisdiction are questions only CRASA and its counsel can answer.
 * Both are flagged needs_content.
 *
 * The accessibility statement is different. It is a statement about this
 * build, and the build is what this seeder knows: the conformance target,
 * the measured exceptions, and how to report a barrier. It is written in
 * full, and it names the two token variations logged in OPEN_ITEMS rather
 * than claiming an unqualified pass.
 */
class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->accessibility();
        $this->placeholderPage('privacy', [
            'en' => 'Privacy notice', 'fr' => 'Avis de confidentialité', 'pt' => 'Aviso de privacidade',
        ], 'Privacy notice: what personal data CRASA collects through this platform, the lawful basis, '
            .'retention periods, who it is shared with, and the jurisdiction. Legal text, from CRASA and its counsel.');

        $this->placeholderPage('terms', [
            'en' => 'Terms of use', 'fr' => "Conditions d'utilisation", 'pt' => 'Termos de utilização',
        ], 'Terms of use: acceptable use, the status of published regulatory text, liability for reliance '
            .'on indicator values, and the governing jurisdiction. Legal text, from CRASA and its counsel.');
    }

    /* The accessibility statement, written in full ------------------------- */

    private function accessibility(): void
    {
        $page = $this->page('accessibility', [
            'en' => ['title' => 'Accessibility statement'],
            'fr' => ['title' => "Déclaration d'accessibilité"],
            'pt' => ['title' => 'Declaração de acessibilidade'],
        ]);

        $body = [
            'en' => "This platform is built to conform to the Web Content Accessibility Guidelines 2.1 at Level AA.\n\n"
                ."What that means in practice here. Every interactive element takes a visible keyboard focus ring, and nothing removes it. Every form field has a real label rather than a placeholder standing in for one. Every page carries one first-level heading, a skip link, and a language attribute that changes with the language you are reading. Charts and matrices have a table equivalent, so nothing depends on seeing a graph. Motion answers an action rather than decorating a page, and a reduced-motion preference is honoured throughout: the banner does not rotate and its controls are removed rather than left inert.\n\n"
                ."Known exceptions. Two colours in the approved design foundations did not meet the 3:1 contrast that success criterion 1.4.11 requires for the boundary of a control. The delivered values differ from the design in order to meet the standard: the border neutral was darkened and the border used on dark surfaces was lightened. A third value, the colour of disabled text, sits below the 4.5:1 body-text threshold and is deliberately unchanged, because success criterion 1.4.3 exempts text that is part of an inactive component.\n\n"
                ."Testing. Contrast is measured against the actual colour values by an automated test rather than assumed, and that test runs on every change. Keyboard operation and screen-reader behaviour are checked manually.\n\n"
                .'If you meet a barrier on this platform, write to the Secretariat at crasa@crasa.org and say which page and what happened. A reply and a fix are treated as the same piece of work.',
            'fr' => "Cette plateforme est conçue pour être conforme aux Règles pour l'accessibilité des contenus Web 2.1, niveau AA.\n\n"
                ."Ce que cela signifie ici. Chaque élément interactif reçoit un indicateur de focus clavier visible, et rien ne le supprime. Chaque champ de formulaire possède une véritable étiquette et non un texte indicatif qui en tiendrait lieu. Chaque page comporte un titre de premier niveau, un lien d'évitement et un attribut de langue qui suit la langue lue. Les graphiques et les matrices ont un équivalent sous forme de tableau : rien ne dépend de la vue. Le mouvement répond à une action plutôt qu'il ne décore, et la préférence de mouvement réduit est respectée partout.\n\n"
                ."Exceptions connues. Deux couleurs des fondations approuvées n'atteignaient pas le contraste de 3:1 exigé par le critère 1.4.11 pour la bordure d'un composant. Les valeurs livrées diffèrent donc de la maquette afin de respecter la norme. Une troisième valeur, celle du texte désactivé, reste inchangée : le critère 1.4.3 exempte le texte faisant partie d'un composant inactif.\n\n"
                ."Tests. Le contraste est mesuré sur les valeurs réelles par un test automatisé exécuté à chaque modification. La navigation au clavier et le comportement avec un lecteur d'écran sont vérifiés manuellement.\n\n"
                ."Si vous rencontrez un obstacle, écrivez au Secrétariat à crasa@crasa.org en indiquant la page et ce qui s'est passé.",
            'pt' => "Esta plataforma foi construída para cumprir as Diretrizes de Acessibilidade para Conteúdo Web 2.1, nível AA.\n\n"
                ."O que isso significa aqui. Cada elemento interativo recebe um indicador de foco de teclado visível, e nada o remove. Cada campo de formulário tem uma etiqueta real e não um texto de exemplo a fazer as suas vezes. Cada página tem um título de primeiro nível, uma ligação para saltar para o conteúdo e um atributo de idioma que acompanha a língua lida. Os gráficos e as matrizes têm equivalente em tabela: nada depende de ver o gráfico. O movimento responde a uma ação em vez de decorar, e a preferência por movimento reduzido é respeitada em toda a plataforma.\n\n"
                ."Exceções conhecidas. Duas cores das fundações aprovadas não atingiam o contraste de 3:1 exigido pelo critério 1.4.11 para o limite de um componente. Os valores entregues diferem do desenho para cumprir a norma. Um terceiro valor, o do texto desativado, mantém-se inalterado: o critério 1.4.3 isenta o texto que faz parte de um componente inativo.\n\n"
                ."Testes. O contraste é medido sobre os valores reais por um teste automatizado que corre a cada alteração. A navegação por teclado e o comportamento com leitor de ecrã são verificados manualmente.\n\n"
                .'Se encontrar uma barreira, escreva ao Secretariado para crasa@crasa.org indicando a página e o que aconteceu.',
        ];

        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::RichText,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['alignment' => 'start'],
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            $block->setTranslations($locale, ['body' => $body[$locale]]);
        }
    }

    /* Privacy and terms: structure now, legal text from CRASA -------------- */

    /** @param array<string, string> $titles */
    private function placeholderPage(string $slug, array $titles, string $note): void
    {
        $page = $this->page($slug, collect($titles)->map(fn ($t) => ['title' => $t])->all());

        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::RichText,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['alignment' => 'start'],
            'needs_content' => true,
            'content_note' => $note,
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            $block->setTranslations($locale, ['body' => __('pages.awaiting_content', [], $locale)]);
        }
    }

    /** @param array<string, array<string, string>> $translations */
    private function page(string $slug, array $translations): Page
    {
        $page = Page::updateOrCreate(
            ['slug' => $slug],
            ['template' => 'legal', 'status' => 'published', 'published_at' => now(), 'is_sample' => false]
        );

        foreach ($translations as $locale => $fields) {
            $page->setTranslations($locale, $fields);
        }

        $page->blocks()->delete();

        return $page;
    }
}
