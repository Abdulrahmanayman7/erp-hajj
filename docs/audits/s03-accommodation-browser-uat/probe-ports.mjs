for (const p of [5173, 5174]) {
  const t = await (await fetch(`http://127.0.0.1:${p}/login`)).text()
  console.log({
    port: p,
    len: t.length,
    loginEmail: t.includes('login-email'),
    vite: t.includes('vite'),
    title: (t.match(/<title>[^<]+/) || [])[0],
  })
}
