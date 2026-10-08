/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['../../../../portal_alumno.php'],
  theme: {
    extend: {
      colors: {
        ibbs: {
          ink: '#1a4d2e',
          ink2: '#1e5c36',
          cream: '#f5f0e8',
          paper: '#fdfaf4',
          lime: '#39ff14',
          lime2: '#2ecc10',
          muted: '#7a8c72',
          border: '#e0d8c8',
          green: '#16a34a',
          red: '#dc2626',
          amber: '#d97706',
          blue: '#2563eb'
        }
      },
      fontFamily: {
        sans: ['Nunito', 'sans-serif'],
        serif: ['Playfair Display', 'serif']
      }
    }
  },
  plugins: []
};
