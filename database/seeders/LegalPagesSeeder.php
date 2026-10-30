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
        /*
         * Drafted, not invented.
         *
         * Both pages are linked from the footer of every page and must not
         * be a holding line in front of fourteen regulators. So each carries
         * a full draft written from what the build actually does: what the
         * platform stores, what a session cookie is for, that publication
         * here gives a document no legal force in any member state, and that
         * a demonstration figure stays badged until its own regulator
         * confirms it.
         *
         * WHAT IS DELIBERATELY BLANK. Retention periods, governing law,
         * jurisdiction, the supervisory authority, whether a data protection
         * officer is appointed, the reuse licence, and any service
         * commitment. Each is a legal commitment rather than a fact about
         * the software, and inventing one would be worse than leaving it
         * open: a wrong retention period on a published privacy notice is a
         * compliance failure CRASA would inherit from us.
         *
         * Each blank is a square-bracketed line naming who must supply it.
         * Both pages carry needs_client_review, which is an administration
         * state: the back office lists the blanks and offers a sign-off, and
         * the public page shows the text and nothing about the state it is
         * in. Until 9 September 2026 each page opened by announcing itself a
         * draft, so every reader of a public legal page met the project's
         * internal state before meeting the text.
         */
        $this->draftPage(
            'privacy',
            ['en' => 'Privacy notice', 'fr' => 'Avis de confidentialité', 'pt' => 'Aviso de privacidade'],
            // Rewritten 30 October 2026 (item 1h): cookie choice, retention
            // the code defines, recipients. One source with the migration.
            require __DIR__.'/data/privacy-notice.php',
            'Privacy notice: a full draft for CRASA\'s legal review, written from what the platform actually does. CRASA and its '
                .'counsel must supply the bracketed items: retention periods, the data controller of record, whether a '
                .'data protection officer is appointed, sharing with COMESA or funders, the hosting location, the '
                .'rights available and the law they arise under, and the supervisory authority.'
        );

        $this->draftPage(
            'terms',
            ['en' => 'Terms of use', 'fr' => "Conditions d'utilisation", 'pt' => 'Termos de utilização'],
            [
                'en' => "What these terms cover. Use of the CRASA Digital Anchor Ecosystem: the public website, the members' extranet and the administration back office.\n\n"
                    ."Acceptable use. You may read, download, quote and redistribute the material published here. You may not attempt to reach material your account is not cleared for, probe the platform for weaknesses without written authorisation, or use automated means that degrade the service for others. Reading and downloading at ordinary human pace is expected and welcome; a crawler that ignores the platform's rate limits is not.\n\n"
                    ."The status of the regulatory text published here. CRASA publishes guidelines, model laws and frameworks that member states adopt into national law through their own processes. **Publication here does not itself give a document legal force in any member state.** Where a document has been superseded the platform says so on the document itself and links to what replaced it; where it is in force the platform says that too. A reader relying on a document should check its status on this platform and confirm its standing with the national regulator concerned.\n\n"
                    ."Indicator values. The comparator and the member state pages carry indicator figures supplied by national regulators. Each figure states its source and the period it covers. **[CRASA and its counsel to state the extent to which CRASA accepts liability for reliance on these figures. The build's position: the figures are reported by member states and CRASA publishes them; it does not warrant them.]**\n\n"
                    ."Translations. The platform publishes in English, French and Portuguese. Where a translation is missing the platform shows the English and says so rather than hiding the record. **[CRASA to confirm which language governs in the event of a discrepancy between versions.]**\n\n"
                    ."Documents and copyright. Every document is served from this platform rather than from a third-party drive. Copyright in a document rests with whoever created it, which for most instruments is CRASA and for some is a member state authority or a partner organisation. **[CRASA to state the licence under which its own material may be reused, and whether attribution is required.]**\n\n"
                    ."Accounts. Accounts are issued by the Secretariat. You are responsible for what is done with yours. Do not share a password or a two-factor device. Tell the Secretariat immediately if you believe an account has been compromised. The Secretariat may suspend an account, and states a reason when it does.\n\n"
                    ."Availability. The platform is provided as it is. CRASA does not undertake that it will be available without interruption. **[CRASA to state any service commitment it wishes to make, or to state explicitly that none is made.]**\n\n"
                    ."Links out. Where the platform links to a member state authority or a partner, that site is that organisation's responsibility and not CRASA's.\n\n"
                    ."Changes to these terms. **[CRASA to state how changes will be communicated and from when they take effect.]**\n\n"
                    ."Governing law and jurisdiction. **[CRASA and its counsel to state the governing law and the courts having jurisdiction. This is not a question the build can answer: CRASA is a Specialised Agency of SADC with fourteen member states, and which law governs its platform is a matter for its own legal framework.]**\n\n"
                    ."Contact. Questions about these terms go to the CRASA Secretariat, whose address is in the footer of every page.\n\n",
                'fr' => "Portée. L'utilisation de l'écosystème numérique de la CRASA : le site public, l'extranet des membres et le back-office d'administration.\n\n"
                    ."Usage acceptable. Vous pouvez lire, télécharger, citer et rediffuser les documents publiés ici. Vous ne pouvez pas tenter d'accéder à des contenus pour lesquels votre compte n'est pas habilité, sonder la plateforme à la recherche de failles sans autorisation écrite, ni employer des moyens automatisés qui dégradent le service pour autrui. La lecture et le téléchargement à un rythme humain sont attendus et bienvenus ; un robot qui ignore les limites de débit ne l'est pas.\n\n"
                    ."Statut des textes réglementaires publiés. La CRASA publie des lignes directrices, des lois types et des cadres de référence que les États membres transposent en droit national par leurs propres procédures. **La publication sur cette plateforme ne confère par elle-même aucune force juridique dans un État membre.** Lorsqu'un document est remplacé, la plateforme l'indique sur le document et renvoie à ce qui le remplace ; lorsqu'il est en vigueur, elle l'indique également. Le lecteur qui s'appuie sur un document doit en vérifier le statut ici et le confirmer auprès du régulateur national concerné.\n\n"
                    ."Valeurs d'indicateurs. Le comparateur et les fiches des États membres présentent des valeurs transmises par les régulateurs nationaux. Chaque valeur indique sa source et la période couverte. **[La CRASA et son conseil doivent préciser dans quelle mesure la CRASA assume une responsabilité en cas d'utilisation de ces valeurs. Position technique : les valeurs sont transmises par les États membres et la CRASA les publie ; elle ne les garantit pas.]**\n\n"
                    ."Traductions. La plateforme publie en anglais, en français et en portugais. Lorsqu'une traduction manque, l'anglais est affiché avec un avis, plutôt que de masquer la fiche. **[La CRASA doit confirmer quelle version fait foi en cas de divergence.]**\n\n"
                    ."Documents et droits d'auteur. Chaque document est servi depuis cette plateforme et non depuis un espace tiers. Les droits appartiennent à leur auteur : la CRASA pour la plupart des instruments, une autorité nationale ou une organisation partenaire pour certains. **[La CRASA doit indiquer la licence de réutilisation de ses propres contenus et si l'attribution est requise.]**\n\n"
                    ."Comptes. Les comptes sont délivrés par le Secrétariat. Vous répondez de l'usage du vôtre. Ne partagez ni mot de passe ni dispositif d'authentification. Signalez immédiatement au Secrétariat toute compromission présumée. Le Secrétariat peut suspendre un compte et en indique alors le motif.\n\n"
                    ."Disponibilité. La plateforme est fournie en l'état. La CRASA ne garantit pas une disponibilité ininterrompue. **[La CRASA doit indiquer tout engagement de service, ou indiquer expressément qu'aucun n'est pris.]**\n\n"
                    ."Liens sortants. Lorsque la plateforme renvoie vers une autorité nationale ou un partenaire, ce site relève de la responsabilité de cette organisation et non de la CRASA.\n\n"
                    ."Modifications. **[La CRASA doit indiquer comment les modifications sont communiquées et à partir de quand elles s'appliquent.]**\n\n"
                    ."Droit applicable et juridiction. **[À déterminer par la CRASA et son conseil. La question dépasse la compétence technique : la CRASA est une agence spécialisée de la SADC comptant quatorze États membres, et le droit applicable à sa plateforme relève de son propre cadre juridique.]**\n\n"
                    ."Contact. Les questions relatives à ces conditions s'adressent au Secrétariat de la CRASA, dont l'adresse figure au pied de chaque page.\n\n",
                'pt' => "Âmbito. A utilização do ecossistema digital da CRASA: o sítio público, o extranet dos membros e o back office de administração.\n\n"
                    ."Utilização aceitável. Pode ler, descarregar, citar e redistribuir o material aqui publicado. Não pode tentar aceder a material para o qual a sua conta não está habilitada, sondar a plataforma à procura de vulnerabilidades sem autorização escrita, nem usar meios automatizados que degradem o serviço para os outros. Ler e descarregar a ritmo humano é esperado e bem-vindo; um robô que ignora os limites de taxa não é.\n\n"
                    ."Estatuto dos textos regulatórios publicados. A CRASA publica orientações, leis-modelo e quadros de referência que os Estados membros transpõem para o direito nacional através dos seus próprios processos. **A publicação nesta plataforma não confere, por si só, força jurídica em nenhum Estado membro.** Quando um documento é substituído, a plataforma indica-o no próprio documento e remete para o que o substituiu; quando está em vigor, também o indica. Quem se apoie num documento deve verificar aqui o seu estatuto e confirmá-lo junto do regulador nacional em causa.\n\n"
                    ."Valores dos indicadores. O comparador e as fichas dos Estados membros apresentam valores comunicados pelos reguladores nacionais. Cada valor indica a sua fonte e o período abrangido. **[A CRASA e a sua assessoria devem indicar em que medida a CRASA assume responsabilidade pela utilização destes valores. Posição técnica: os valores são comunicados pelos Estados membros e a CRASA publica-os; não os garante.]**\n\n"
                    ."Traduções. A plataforma publica em inglês, francês e português. Quando falta uma tradução, é apresentado o inglês com um aviso, em vez de ocultar o registo. **[A CRASA deve confirmar qual a versão que prevalece em caso de divergência.]**\n\n"
                    ."Documentos e direitos de autor. Cada documento é servido a partir desta plataforma e não de um espaço de terceiros. Os direitos pertencem a quem os criou: a CRASA na maioria dos instrumentos, uma autoridade nacional ou uma organização parceira noutros. **[A CRASA deve indicar a licença de reutilização do seu próprio material e se é exigida atribuição.]**\n\n"
                    ."Contas. As contas são emitidas pelo Secretariado. É responsável pelo que for feito com a sua. Não partilhe palavra-passe nem dispositivo de autenticação. Comunique imediatamente ao Secretariado qualquer suspeita de compromisso. O Secretariado pode suspender uma conta, indicando o motivo.\n\n"
                    ."Disponibilidade. A plataforma é fornecida no estado em que se encontra. A CRASA não garante disponibilidade ininterrupta. **[A CRASA deve indicar qualquer compromisso de serviço, ou indicar expressamente que não assume nenhum.]**\n\n"
                    ."Ligações externas. Quando a plataforma remete para uma autoridade nacional ou um parceiro, esse sítio é da responsabilidade dessa organização e não da CRASA.\n\n"
                    ."Alterações. **[A CRASA deve indicar como as alterações são comunicadas e a partir de quando produzem efeito.]**\n\n"
                    ."Lei aplicável e jurisdição. **[A determinar pela CRASA e pela sua assessoria. A questão excede a competência técnica: a CRASA é uma Agência Especializada da SADC com catorze Estados membros, e a lei aplicável à sua plataforma decorre do seu próprio quadro jurídico.]**\n\n"
                    ."Contacto. As questões sobre estes termos dirigem-se ao Secretariado da CRASA, cuja morada consta do rodapé de cada página.\n\n",
            ],
            'Terms of use: a full draft is in place. CRASA and its counsel must supply the bracketed items: liability '
                .'for reliance on indicator values, which language governs on a discrepancy, the reuse licence, any '
                .'service commitment, how changes are communicated, and the governing law and jurisdiction.'
        );
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
            'en' => "This platform is built to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA.\n\n"
                ."What that means in practice here. Every interactive element takes a visible keyboard focus ring, and nothing removes it. Every form field has a real label rather than a placeholder standing in for one. Every page carries one first-level heading, a skip link, and a language attribute that changes with the language you are reading. Charts and matrices have a table equivalent, so nothing depends on seeing a graph. Motion answers an action rather than decorating a page, and a reduced-motion preference is honoured throughout: the banner does not rotate and its controls are removed rather than left inert.\n\n"
                ."Known exceptions. Two colours in the approved design foundations did not meet the 3:1 contrast that success criterion 1.4.11 requires for the boundary of a control. The delivered values differ from the design in order to meet the standard: the border neutral was darkened and the border used on dark surfaces was lightened. A third value, the colour of disabled text, sits below the 4.5:1 body-text threshold and is deliberately unchanged, because success criterion 1.4.3 exempts text that is part of an inactive component.\n\n"
                ."Testing. Contrast is measured against the actual colour values by an automated test rather than assumed, and that test runs on every change. Keyboard operation and screen-reader behaviour are checked manually.\n\n"
                .'If you meet a barrier on this platform, write to the Secretariat at crasa@crasa.org and say which page and what happened. A reply and a fix are treated as the same piece of work.',
            'fr' => "Cette plateforme est conçue pour être conforme aux Règles pour l'accessibilité des contenus Web (WCAG) 2.1, niveau AA.\n\n"
                ."Ce que cela signifie ici. Chaque élément interactif reçoit un indicateur de focus clavier visible, et rien ne le supprime. Chaque champ de formulaire possède une véritable étiquette et non un texte indicatif qui en tiendrait lieu. Chaque page comporte un titre de premier niveau, un lien d'évitement et un attribut de langue qui suit la langue lue. Les graphiques et les matrices ont un équivalent sous forme de tableau : rien ne dépend de la vue. Le mouvement répond à une action plutôt qu'il ne décore, et la préférence de mouvement réduit est respectée partout.\n\n"
                ."Exceptions connues. Deux couleurs des fondations approuvées n'atteignaient pas le contraste de 3:1 exigé par le critère 1.4.11 pour la bordure d'un composant. Les valeurs livrées diffèrent donc de la maquette afin de respecter la norme. Une troisième valeur, celle du texte désactivé, reste inchangée : le critère 1.4.3 exempte le texte faisant partie d'un composant inactif.\n\n"
                ."Tests. Le contraste est mesuré sur les valeurs réelles par un test automatisé exécuté à chaque modification. La navigation au clavier et le comportement avec un lecteur d'écran sont vérifiés manuellement.\n\n"
                ."Si vous rencontrez un obstacle, écrivez au Secrétariat à crasa@crasa.org en indiquant la page et ce qui s'est passé.",
            'pt' => "Esta plataforma foi construída para cumprir as Diretrizes de Acessibilidade para Conteúdo Web (WCAG) 2.1, nível AA.\n\n"
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
    /**
     * A page carrying real drafted copy that still needs CRASA's sign-off.
     *
     * needs_content stays true on the block, so it stays on the
     * placeholder list, and needs_client_review goes on the page, which is
     * what the back office and the dashboard read. Neither reaches a public
     * template. What a reader meets is a complete document rather than a
     * holding line, which is the right state for a page linked from the
     * footer of every page on the site.
     *
     * @param  array<string, string>  $titles
     * @param  array<string, string>  $bodies
     */
    private function draftPage(string $slug, array $titles, array $bodies, string $note): void
    {
        $page = $this->page($slug, collect($titles)->map(fn ($title) => ['title' => $title])->all());

        // Administration only. Cleared by an administrator marking the page
        // reviewed, which stamps who and when.
        $page->forceFill(['needs_client_review' => true])->save();

        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::RichText,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['alignment' => 'start'],
            'needs_content' => true,
            'content_note' => $note,
        ]);

        foreach ($bodies as $locale => $bodyText) {
            $block->setTranslations($locale, ['body' => $bodyText]);
        }
    }

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

        // purge(), not delete(). See HasTranslations::scopePurge.
        $page->blocks()->purge();

        return $page;
    }
}
