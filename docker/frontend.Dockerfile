FROM node:22-alpine

WORKDIR /app

# Copiamos los archivos de configuración primero
COPY package*.json ./

# Instalamos las dependencias
RUN npm install

# Copiamos el resto del código
COPY . .

# Instalamos Angular CLI globalmente
RUN npm install -g @angular/cli@20

EXPOSE 4200

# Comando para arrancar
CMD ["ng", "serve", "--host", "0.0.0.0", "--poll", "2000"]
