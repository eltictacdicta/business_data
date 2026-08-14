# business_data

Plugin de datos empresariales básicos para [FSFramework](https://github.com/eltictacdicta/fs-framework).

Repositorio: https://github.com/eltictacdicta/business_data

## Qué incluye

- Empresa (`empresa`)
- Ejercicios contables (`ejercicio`)
- Series de documentos (`serie`)
- Formas de pago (`forma_pago`)
- Cuentas bancarias de la empresa (`cuenta_banco`)

Las cuentas bancarias de clientes (`cuenta_banco_cliente`, tabla `cuentasbcocli`) viven en **`clientes_core`** desde la versión 3 del plugin.

Divisas, almacenes y países pertenecen a **`catalogo_core`**. Este plugin no los implementa ni los carga; `admin_empresa` ofrece campos de texto cuando catálogo no está activo.

## Dependencias

```ini
require = ""
```

No requiere otros plugins. Para selects de divisa/almacén/país en empresa y conversión de moneda, activa **`catalogo_core`** (gestiona `fs_divisa_tools`).

Orden de activación recomendado en instalaciones completas:

1. `catalogo_core`
2. `business_data`
3. Resto según grafo (`clientes_core`, etc.)

## Instalación

```bash
git clone https://github.com/eltictacdicta/business_data.git plugins/business_data
```

O desde el panel: **Admin → Tienda de Plugins → Descargar**.

## Licencia

LGPL-3.0-or-later
