# business_data

Plugin de datos empresariales básicos para [FSFramework](https://github.com/eltictacdicta/fs-framework).

Repositorio: https://github.com/eltictacdicta/business_data

## Qué incluye

- Empresa (`empresa`)
- Ejercicios contables (`ejercicio`)
- Series de documentos (`serie`)
- Formas de pago (`forma_pago`)
- Cuentas bancarias (`cuenta_banco`, `cuenta_banco_cliente`)
- Proxies legacy para `divisa`, `almacen` y `pais` (implementación real en `catalogo_core`)

## Dependencias

```ini
require = "catalogo_core"
```

Orden de activación recomendado:

1. `catalogo_core`
2. `business_data`

## Instalación

```bash
git clone https://github.com/eltictacdicta/business_data.git plugins/business_data
```

O desde el panel: **Admin → Tienda de Plugins → Descargar**.

## Licencia

LGPL-3.0-or-later
