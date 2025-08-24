=== Notificaciones WhatsApp para WooCommerce por SMSenlinea ===
Contributors: gemini-google
Tags: woocommerce, whatsapp, notifications, smsenlinea, automation
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Envía notificaciones de WhatsApp a los clientes en eventos de WooCommerce usando la API de SMSenlinea.com.

== Description ==

Este plugin integra tu tienda de WooCommerce con la plataforma de SMSenlinea.com para enviar mensajes de WhatsApp automáticos a tus clientes. Puedes configurar notificaciones para:

* Nuevos pedidos.
* Cambios en el estado de los pedidos.
* Cuando agregas una nota para el cliente en un pedido.

Personaliza completamente los mensajes y utiliza variables dinámicas para incluir información del pedido.

== Installation ==

1.  Sube la carpeta `smsenlinea-whatsapp-woocommerce` a tu directorio `/wp-content/plugins/`.
2.  Activa el plugin a través del menú 'Plugins' en WordPress.
3.  Ve a `WooCommerce > WhatsApp SMSenlinea` para configurar tus credenciales de la API y las plantillas de mensajes.

== Changelog ==

= 2.0.0 =
* Refactorización completa a una estructura orientada a objetos.
* Separación de responsabilidades en clases (API, Admin, Hooks).
* Añadido soporte para internacionalización.
* Mejora de la seguridad y las mejores prácticas de WordPress.

= 1.0.0 =
* Lanzamiento inicial.
