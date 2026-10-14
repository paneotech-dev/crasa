<?php

/*
 * Names held in a single database column (countries, sectors, committees,
 * indicators), in Portuguese. Read by App\Models\Concerns\HasLocalisedNames.
 *
 * MACHINE TRANSLATION, 14 October 2026, not yet reviewed by a Portuguese
 * speaker: docs/TRANSLATION_REVIEW.md lists every entry for review.
 * Indicator names follow the UIT and UPU Portuguese terminology where it exists.
 */
return [
    'countries' => [
        'ao' => 'Angola',
        'bw' => 'Botsuana',
        'km' => 'Comores',
        'cd' => 'RD Congo',
        'sz' => 'Essuatíni',
        'ls' => 'Lesoto',
        'mw' => 'Malawi',
        'mu' => 'Maurícia',
        'mz' => 'Moçambique',
        'na' => 'Namíbia',
        'za' => 'África do Sul',
        'tz' => 'Tanzânia',
        'zm' => 'Zâmbia',
        'zw' => 'Zimbabwe',
        'mg' => 'Madagáscar',
        'sc' => 'Seicheles',
    ],
    'sectors' => [
        'telecommunications' => 'Telecomunicações',
        'broadcasting' => 'Radiodifusão',
        'postal' => 'Serviços postais',
    ],
    'committees' => [
        'spectrum-and-frequency-management' => 'Gestão do Espetro e das Frequências',
        'postal-and-logistics' => 'Correios e Logística',
        'legal-and-regulatory-affairs' => 'Assuntos Jurídicos e Regulamentares',
        'economics' => 'Economia',
        'consumer-affairs-and-protection' => 'Assuntos e Proteção do Consumidor',
        'human-and-institutional-capacity' => 'Capacidade Humana e Institucional',
    ],
    'indicators' => [
        'fixed-telephone-subscriptions' => [
            'name' => 'Assinaturas de telefone fixo por 100 habitantes',
            'definition' => 'Soma das linhas telefónicas fixas analógicas ativas, das assinaturas de voz sobre IP (VoIP), das assinaturas de lacete local sem fios fixo (WLL), dos equivalentes em canais de voz RDIS e dos telefones públicos fixos, dividida pela população e multiplicada por 100. Anteriormente designadas linhas telefónicas principais em serviço.',
        ],
        'mobile-cellular-subscriptions' => [
            'name' => 'Assinaturas de telefone móvel celular por 100 habitantes',
            'definition' => 'Assinaturas de um serviço público de telefonia móvel que dá acesso à RTPC através de tecnologia celular, divididas em assinaturas pós-pagas e contas pré-pagas ativas utilizadas nos últimos três meses. Aplica-se a todas as assinaturas que oferecem comunicações de voz. Excluem-se as placas de dados e modems USB, os serviços públicos de dados móveis, a rádio móvel privada de recursos partilhados, o telepoint, a radiochamada, as comunicações M2M e a telemetria. Divididas pela população e multiplicadas por 100.',
        ],
        'fixed-broadband-subscriptions' => [
            'name' => 'Assinaturas de banda larga fixa por 100 habitantes',
            'definition' => 'Assinaturas fixas de acesso de alta velocidade à Internet pública, uma ligação TCP/IP, com velocidades descendentes iguais ou superiores a 256 kbit/s. Inclui modem por cabo, DSL, fibra até à casa ou ao edifício, banda larga por satélite, banda larga sem fios fixa terrestre e WiMAX fixo, residenciais e empresariais, independentemente da forma de pagamento. Exclui o acesso a comunicações de dados através de redes móveis celulares. Divididas pela população e multiplicadas por 100.',
        ],
        'active-mobile-broadband-subscriptions' => [
            'name' => 'Assinaturas ativas de banda larga móvel por 100 habitantes',
            'definition' => 'Soma das assinaturas ativas de banda larga móvel à Internet pública, em telemóvel ou em computador. Abrange os assinantes efetivos, não os potenciais. Uma assinatura tem de incluir uma mensalidade recorrente ou ter acedido à Internet nos últimos três meses. Inclui redes com velocidades de descarga de pelo menos 256 kbit/s; exclui as assinaturas com acesso apenas a GPRS, EDGE ou CDMA 1xRTT. Divididas pela população e multiplicadas por 100.',
        ],
        'international-bandwidth-per-inhabitant' => [
            'name' => 'Utilização de largura de banda internacional por habitante',
            'definition' => 'Utilização média de todas as ligações internacionais, incluindo cabos de fibra ótica, ligações de rádio e o tráfego processado por estações terrenas de satélite e teleportos, em média ao longo dos doze meses do ano de referência. Quando o tráfego é assimétrico, utiliza-se o maior dos valores de entrada e de saída. São contados todos os tipos de operador. Dividida pela população total.',
        ],
        'population-covered-3g' => [
            'name' => 'Percentagem da população coberta por pelo menos uma rede móvel 3G',
            'definition' => 'Percentagem de habitantes ao alcance de pelo menos um sinal móvel celular 3G, sejam ou não assinantes. Calcula-se dividindo o número de habitantes cobertos por pelo menos um sinal 3G pela população total e multiplicando por 100. Exclui as pessoas cobertas apenas por GPRS, EDGE ou CDMA 1xRTT.',
        ],
        'multichannel-tv-subscriptions' => [
            'name' => 'Assinaturas de televisão multicanal por 100 habitantes',
            'definition' => 'Serviços que oferecem programação televisiva para além dos canais terrestres de acesso livre: televisão por cabo, satélite direto ao domicílio, televisão por protocolo Internet e televisão digital terrestre. Contados apenas quando há canais adicionais disponíveis mediante pagamento. Divididas pela população e multiplicadas por 100.',
        ],
        'licensed-postal-operators' => [
            'name' => 'Número de operadores postais licenciados, autorizados ou declarados',
            'definition' => 'Operadores que prestam serviços de distribuição de cartas, encomendas e envios expresso, não sendo necessariamente responsáveis pela prestação do serviço universal nem pelo cumprimento das obrigações decorrentes dos Atos da UPU.',
        ],
        'permanent-post-offices' => [
            'name' => 'Número de estações de correios permanentes',
            'definition' => 'Todas as estações abertas ao público e em funcionamento em instalações fixas, ou seja, a soma das estações servidas por funcionários do operador designado e das estações servidas por pessoas sem vínculo ao operador designado. Exclui as estações de correios móveis.',
        ],
        'letter-post-items-domestic' => [
            'name' => 'Objetos de correspondência, serviço doméstico',
            'definition' => 'Volume total de correspondência processada pelo operador designado no seu serviço doméstico. Inclui, em princípio, os objetos ordinários e os objetos com tratamento especial, ou seja, registados, cartas com valor declarado e jornais, bem como objetos publicitários e correio híbrido.',
        ],
        'postal-parcels-domestic' => [
            'name' => 'Encomendas postais, serviço doméstico',
            'definition' => 'Número de encomendas postais ordinárias e com valor declarado processadas pelo operador designado no seu serviço doméstico.',
        ],
        'postal-staff-total' => [
            'name' => 'Número total de trabalhadores postais',
            'definition' => 'Soma dos trabalhadores a tempo inteiro e a tempo parcial, ou seja, o número de pessoas singulares empregadas pelo operador designado e não o número de postos ocupados em equivalentes a tempo inteiro. Exclui as pessoas empregadas por subcontratados e o pessoal temporário contratado para períodos de férias ou eventos ocasionais.',
        ],
        'postal-transit-time-standard' => [
            'name' => 'Prazo de encaminhamento previsto de ponta a ponta para a correspondência prioritária doméstica',
            'definition' => 'Prazo de encaminhamento previsto de ponta a ponta para os objetos de correspondência prioritária do serviço doméstico, normalmente um, dois ou três dias após a aceitação. Quando se aplicam normas diferentes a diferentes percursos no país, utiliza-se a mais curta. Quando se aplicam várias normas ao mesmo percurso, utiliza-se a mais curta.',
        ],
        'postal-items-within-standard' => [
            'name' => 'Percentagem de objetos de correspondência entregues dentro da norma de qualidade',
            'definition' => 'Percentagem de objetos efetivamente entregues dentro do prazo de encaminhamento da norma, ou seja, o resultado anual de uma medição da qualidade do serviço doméstico no ano em causa.',
        ],
    ],
];
