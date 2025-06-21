<?php

namespace App\Services;

class SeoService
{
    protected array $defaults = [
        'title' => 'Darink.App – Alimentación con conciencia',
        'description' => 'Darink.App te guía hacia una alimentación sana, consciente y compasiva. Un espacio seguro para mejorar tu bienestar.',
        'keywords' => 'TCA, alimentación consciente, salud mental, trastornos alimentarios, bienestar, autocuidado, app de salud',
        'image' => '/images/og-image.png',
        'url' => null, // Se completará automáticamente con current_url()
    ];

    protected array $data = [];

    public function set(array $values): self
    {
        $this->data = array_merge($this->defaults, $values);
        return $this;
    }

    public function get(): array
    {
        // Asegura que la URL sea la actual si no se proporciona
        if (empty($this->data['url'])) {
            $this->data['url'] = current_url();
        }

        // Asegura que la imagen tenga la URL completa
        if (!str_starts_with($this->data['image'], 'http')) {
            $this->data['image'] = base_url($this->data['image']);
        }

        return $this->data;
    }
}
