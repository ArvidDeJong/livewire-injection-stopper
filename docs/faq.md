---
title: FAQ
nav_order: 8
description: Short answers about darvis/livewire-injection-stopper, bot blocking and Livewire payload protection for Laravel apps.
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
