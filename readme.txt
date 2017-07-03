=== WooCommerce Shipping Gateway ===
Contributors: frenet
Donate link: http://www.frenet.com.br/
Tags: shipping, delivery, woocommerce, correios, jamef, jadlog, tnt, braspress
Requires at least: 3.5
Tested up to: 4.6
Stable tag: 2.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds Correios, Jamef, Jadlog, TNT, Total Express, Direct Log, Braspress and others shipping options to the WooCommerce plugin

== Description ==

### Add Correios, Jamef, Jadlog, TNT, Braspress, Direct Log, Total Express, Exporta Fácil shipping to WooCommerce ###

The Frenet freight shipping gateway is able to integrate and process tables of freight carriers and brazilian Correios. The freight calculation for the brazilian Correios services is done on-line (via Correios webservice) or in case of unavailability, uses database in the cloud highly available and regularly updated automatically, with the values of quotations.

It provides to the store owners, major carriers in Brazil to transport large volumes as well as Jamef, Jadlog, TNT, Braspress, Direct among others

Please notice that WooCommerce must be installed and active.

### Descrição em Português: ###

O gateway de fretes Frenet é capaz de integrar e processar tabelas de fretes de transportadoras e Correios. O cálculo de frete para os serviços dos Correios é feito de forma online (via webservice dos Correios) ou em caso de indisponibilidade, utiliza base de dados na nuvem altamente disponível e regularmente atualizada de forma automática, com os valores das cotações.

Disponibiliza aos lojistas as principais transportadoras do Brasil para transporte de grandes volumes, assim como Jamef, Jadlog, TNT, Braspress, Direct Log, Total Express, Exporta Fácil entre outras.

[Frenet](http://www.frenet.com.br/)

= Instalação: =

Confira o nosso guia de instalação e configuração na aba [Installation](http://wordpress.org/plugins/woo-shipping-gateway/installation/).

= Dúvidas? =

Você pode esclarecer suas dúvidas usando:

* Central de ajuda [Central de ajuda](https://frenet.zendesk.com).
* Criando um tópico no [fórum de ajuda do WordPress](http://wordpress.org/support/plugin/woo-shipping-gateway) (apenas em inglês).

== Installation ==

* Upload plugin files to your plugins folder, or install using WordPress built-in Add New Plugin installer;
* Activate the plugin;
* Navigate to WooCommerce -> Settings -> Shipping, choose Frenet and fill settings.
* Register to the back end (https://painel.frenet.com.br) and get an access key.

### Instalação e configuração em Português: ###

= Instalação do plugin: =

* Envie os arquivos do plugin para a pasta wp-content/plugins, ou instale usando o instalador de plugins do WordPress.
* Ative o plugin.
* Cadastre-se no Painel Administrativo (https://painel.frenet.com.br) e obtenha uma chave de acesso.

= Requerimentos: =

Possuir instalado a extensão SimpleXML (que já é instalado por padrão com o PHP 5).

= Configurações do plugin: =

Com o plugin instalado navegue até "WooCommerce" > "Configurações" > "Entrega" > "Frenet".

Nesta tela configure a sua **Chave de acesso** e **Senha**.

Também é possível configurar um **Pacote Padrão** que será utilizando para definir as medidas mínimas do pacote de entraga.

= Configurações dos produtos =

Para que seja possível cotar o frete, os seus produtos precisam ser do tipo **simples** ou **variável** e não estarem marcados com *virtual* ou *baixável* (qualquer outro tipo de produto será ignorado na cotação).

É necessário configurar o **peso** e **dimensões** de todos os seus produtos, caso você queria que a cotação de frete seja exata.
Alternativamente, você pode configurar apenas o peso e deixar as dimensões em branco, pois neste caso serão utilizadas as configurações do **Pacote Padrão** para as dimensões (neste caso pode ocorrer uma variação no valor do frete).

== Screenshots ==

1. Configurações do plugin.

== Changelog ==

= 2.1.2 - 03/07/2017 =

* Bug Fix - Correção post data Json;

= 2.1.1 - 12/01/2017 =

* Bug Fix - Cálculo na página do produto - valor e quantidade; Quantidade do mesmo produto;

= 2.1.0 - 07/11/2016 =

* Adicionado cálculo de frete na página do produto - Grande contribuição de Bruno Rodrigues (https://github.com/bruno-rodrigues) e Flávia Amaral (https://github.com/flavia-programmer)

= 2.0.1 =

* Versão compativel com Woocommerce 2.6.x - Shipping zones; Bug Fixes - Token

= 2.0.0 =

* Versão compativel com Woocommerce 2.6.x - Shipping zones

= 1.0.2 =

* Bug Fix - Tradução

= 1.0.1 =

* Bug Fixes

= 1.0.0 =

* Versão inicial do plugin.

== Upgrade Notice ==

= 2.1.2 - 03/07/2017 =

* Bug Fix - Correção post data Json;

= 2.1.1 - 12/01/2017 =

* Bug Fix - Cálculo na página do produto - valor e quantidade; Quantidade do mesmo produto;

== Frequently Asked Questions ==

= What is the plugin license? =

* This plugin is released under a GPL license.

### FAQ em Português: ###

= Qual é a licença do plugin? =

Este plugin esta licenciado como GPL.

= O que eu preciso para utilizar este plugin? =

* Ter instalado o plugin WooCommerce.
* Possuir instalado em sua hospedagem a extensão de SimpleXML.
* Configurar o seu acesso nas configurações do plugin.
* Adicionar peso e dimensões nos produtos que pretende entregar.

**Atenção**: É obrigatório ter o **peso** configurado em cada produto para que seja possível cotar o frete de forma eficiente. As dimensões podem ficar em branco e neste caso, serão utilizadas as medidas da opção **Pacote Padrão** da configuração do plugin, mas é **recomendado** que cada produto tenha suas configurações próprias de **peso** e **dimensões**.

== License ==

WooCommerce Frenet is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published
by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

WooCommerce Frenet is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with WooCommerce Frenet. If not, see <http://www.gnu.org/licenses/>.
